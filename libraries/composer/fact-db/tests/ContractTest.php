<?php

declare(strict_types=1);

namespace FactDb\Tests;

use Closure;
use DateTimeImmutable;
use FactDb\FactDb;
use FactDb\FactDbException;
use FactDb\Failure;
use FactDb\Model\{PropositionContent, Qualifier, ClaimDraft, KnowledgeSnapshot, KnowledgeState, Limits, Proposition, PropositionDraft, PropositionStatus, ReconciliationPlan, Reference, Scope, SourceReference, SourceVersion};
use FactDb\Persistence\{RecordCodec, SqliteRepository, SqliteSchema};
use FactDb\Port\{Clock, Identifiers, SourceAnalyzer};
use PDO;
use RuntimeException;
use Throwable;

final class ContractTest
{
    private int $operation = 0;

    public function run(): int
    {
        $cases = ['manyToMany', 'immutableIdentity', 'operationReplay', 'oldEdit', 'independentSupport',
            'contextEdit', 'transitiveImpact', 'conflictingActors', 'invalidPlans', 'supersededDependency',
            'reanalysis', 'scopeIsolation', 'restart', 'concurrentSnapshots', 'analyzerBoundary',
            'rollback', 'durableImmutability', 'schemaOwnership', 'capacity', 'erasure', 'checkedRecords', 'serialization', 'batchReconciliation', 'batchRollback', 'analysisContract', 'sourceSynchronization', 'summaryAttribution', 'analysisReferences', 'analysisAliases', 'structuredContent', 'structuredCorrection', 'legacyUpgrade', 'hostCommitGuard'];
        foreach ($cases as $case) {
            try {
                $this->$case();
            } catch (Throwable $error) {
                throw new RuntimeException('FactDB ' . $case . ': ' . $error->getMessage(), 0, $error);
            }
        }
        return count($cases);
    }

    /** @return array{FactDb, PDO, SqliteRepository, Clock, Identifiers} */
    private function fixture(?Limits $limits = null, ?PDO $pdo = null, ?Scope $scope = null): array
    {
        $pdo ??= $this->connection();
        SqliteSchema::install($pdo);
        $repository = new SqliteRepository($pdo, $limits ?? new Limits());
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-10-18T10:00:00Z'); }
        };
        $identifiers = new class implements Identifiers {
            private int $next = 0;
            public function next(): string { return 'id:' . ++$this->next; }
        };
        return [new FactDb($scope ?? new Scope('tenant', 'case'), $repository, $clock, $identifiers), $pdo, $repository, $clock, $identifiers];
    }

    private function connection(string $dsn = 'sqlite::memory:'): PDO
    {
        $pdo = new PDO($dsn, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    private function source(string $id, int $position, string $text, int $version = 1, string $actor = 'client'): SourceVersion
    {
        return new SourceVersion(new SourceReference($id, $version), $position, $actor, $text, '2026-10-18T10:00:00Z');
    }

    private function register(FactDb $db, SourceVersion $source): void
    {
        $db->register($source, $db->snapshot()->revision);
    }

    // Existing orchestration fixtures keep their wording; semantic decomposition has separate cases.
    private function content(string $statement): PropositionContent
    {
        $parts = explode(' ', $statement, 2);
        return new PropositionContent($parts[0], $parts[1] ?? 'is stated');
    }

    private function draft(string $key, string $statement): PropositionDraft
    {
        return new PropositionDraft($key, $this->content($statement));
    }

    /** @param list<string> $statements */
    private function review(FactDb $db, SourceReference $source, array $statements): KnowledgeSnapshot
    {
        $drafts = $claims = [];
        foreach ($statements as $index => $statement) {
            $key = 'p:' . $index;
            $drafts[] = $this->draft($key, $statement);
            $claims[] = new ClaimDraft('c:' . $index, Reference::proposed($key));
        }
        return $db->apply($this->plan($db, $source, $drafts, $claims));
    }

    /** @param list<PropositionDraft> $drafts @param list<ClaimDraft> $claims */
    private function plan(FactDb $db, SourceReference $source, array $drafts, array $claims): ReconciliationPlan
    {
        return new ReconciliationPlan('operation:' . ++$this->operation, $db->snapshot()->revision, $source, 'review:fixture', $drafts, $claims);
    }

    private function fact(KnowledgeSnapshot $snapshot, string $statement): \FactDb\Model\FactView
    {
        foreach ($snapshot->facts as $fact) {
            if ($fact->proposition->statement === $statement || $fact->proposition->identity() === $this->content($statement)->identity()) {
                return $fact;
            }
        }
        throw new RuntimeException('Expected proposition was not present.');
    }

    private function check(bool $condition, string $message = 'Assertion failed'): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    private function failure(Failure $reason, Closure $work): void
    {
        try {
            $work();
        } catch (FactDbException $error) {
            $this->check($error->reason === $reason, 'Unexpected failure: ' . $error->reason->value . ' (' . $error->getMessage() . ')');
            return;
        }
        throw new RuntimeException('Expected failure was not raised: ' . $reason->value);
    }

    private function manyToMany(): void
    {
        [$db, $pdo] = $this->fixture();
        $one = $this->source('m1', 1, 'Rent is 12; deposit is 200.');
        $this->register($db, $one);
        $view = $this->review($db, $one->reference, ['Rent is 12.', 'Deposit is 200.']);
        $deposit = $this->fact($view, 'Deposit is 200.')->proposition->id;
        $two = $this->source('m2', 2, 'Deposit is 200.');
        $this->register($db, $two);
        $view = $this->review($db, $two->reference, ['Deposit is 200.']);
        $this->check(count($view->facts) === 2 && count($view->forSummary()->facts) === 2);
        $this->check($this->fact($view, 'Deposit is 200.')->proposition->id === $deposit);
        $this->check(count($this->fact($view, 'Deposit is 200.')->claims) === 2);
        $this->check((int) $pdo->query("SELECT COUNT(DISTINCT proposition_id) FROM factdb_claims WHERE source_id = 'm1'")->fetchColumn() === 2);
        $this->check((int) $pdo->query('SELECT COUNT(*) FROM factdb_evidence')->fetchColumn() === 3);
        $three = $this->source('m3', 3, 'The deposit amounts to two hundred.');
        $this->register($db, $three);
        $view = $db->apply($this->plan($db, $three->reference, [], [new ClaimDraft('same-meaning', Reference::existing($deposit))]));
        $this->check(count($view->facts) === 2 && count($view->facts[$deposit]->claims) === 3);
    }

    private function immutableIdentity(): void
    {
        [$db] = $this->fixture();
        $source = $this->source('m1', 1, "  Exact source text.\n");
        $this->register($db, $source);
        $view = $this->review($db, $source->reference, ['Rent is 10.']);
        $old = $this->fact($view, 'Rent is 10.')->proposition;
        $this->check($view->sources['m1']->text === "  Exact source text.\n");
        $again = $db->register($source, 0);
        $this->check($again->revision === $view->revision);
        $this->failure(Failure::ImmutableRecord, fn () => $db->register($this->source('m1', 1, 'Changed.'), $view->revision));
        $this->register($db, $this->source('m1', 1, 'Rent is 12.', 2));
        $after = $this->review($db, new SourceReference('m1', 2), ['Rent is 12.']);
        $this->check($after->facts[$old->id]->proposition == $old);
        $this->check($after->facts[$old->id]->proposition->statement === 'Rent is 10.');
        $this->check($this->fact($after, 'Rent is 12.')->proposition->id !== $old->id);
        $this->check(!property_exists($old, 'revision'));
    }

    private function operationReplay(): void
    {
        [$db, $pdo] = $this->fixture();
        $source = $this->source('1', 1, 'One fact.');
        $this->register($db, $source);
        $plan = new ReconciliationPlan('1', $db->snapshot()->revision, $source->reference, 'manual',
            [$this->draft('1', 'One fact.')], [new ClaimDraft('1', Reference::proposed('1'))]);
        $first = $db->apply($plan);
        $this->check($db->apply($plan)->revision === $first->revision);
        $this->check((int) $pdo->query('SELECT COUNT(*) FROM factdb_claims')->fetchColumn() === 1);
        $changed = new ReconciliationPlan('1', $plan->expectedRevision, $source->reference, 'other-review', [], []);
        $this->failure(Failure::InvalidPlan, fn () => $db->apply($changed));
        $this->review($db, $source->reference, []);
        $this->check(count($db->apply($plan)->forSummary()->facts) === 0, 'Old replay must not restore an obsolete analysis.');
    }

    private function oldEdit(): void
    {
        [$db] = $this->fixture();
        $first = $this->source('m1', 1, 'Initial rent is 10.');
        $this->register($db, $first);
        $view = $this->review($db, $first->reference, ['Rent is 10.']);
        $oldClaim = $this->fact($view, 'Rent is 10.')->claims[0]->id;
        $final = $this->source('m2', 2, 'Final correction: rent is 12.');
        $this->register($db, $final);
        $view = $db->apply($this->plan($db, $final->reference, [$this->draft('rent', 'Rent is 12.')],
            [new ClaimDraft('c', Reference::proposed('rent'), supersedes: [Reference::existing($oldClaim)])]));
        $finalId = $this->fact($view, 'Rent is 12.')->proposition->id;
        $this->register($db, $this->source('m1', 1, 'Initial estimate was 9.', 2));
        $view = $db->snapshot();
        $this->check(array_map(fn (SourceReference $ref): string => $ref->id, $view->pendingSources) === ['m1', 'm2']);
        $this->failure(Failure::Incomplete, fn () => $view->forSummary());
        $view = $this->review($db, new SourceReference('m1', 2), ['Initial rent estimate is 9.']);
        $initialClaim = $this->fact($view, 'Initial rent estimate is 9.')->claims[0]->id;
        $view = $db->apply($this->plan($db, $final->reference, [], [new ClaimDraft('c', Reference::existing($finalId), supersedes: [Reference::existing($initialClaim)])]));
        $this->check($view->complete && $view->facts[$finalId]->status === PropositionStatus::Supported);
        $this->check($this->fact($view, 'Rent is 10.')->status === PropositionStatus::Unsupported);
        $this->check($this->fact($view, 'Initial rent estimate is 9.')->status === PropositionStatus::Superseded);
        $currentClaim = $view->facts[$finalId]->claims[0]->id;
        $bad = $this->plan($db, new SourceReference('m1', 2), [$this->draft('bad', 'Rent is 8.')],
            [new ClaimDraft('c', Reference::proposed('bad'), supersedes: [Reference::existing($currentClaim)])]);
        $this->failure(Failure::InvalidPlan, fn () => $db->apply($bad));
        $this->check($db->snapshot()->revision === $view->revision);
    }

    private function independentSupport(): void
    {
        [$db] = $this->fixture();
        foreach (['m1', 'm2'] as $index => $id) {
            $source = $this->source($id, $index + 1, 'Deposit is 200.');
            $this->register($db, $source);
            $view = $this->review($db, $source->reference, ['Deposit is 200.']);
        }
        $id = $this->fact($view, 'Deposit is 200.')->proposition->id;
        $view = $db->withdraw('m1', 1, $view->revision);
        $this->check($view->complete && count($view->facts[$id]->claims) === 1);
        $view = $db->withdraw('m2', 1, $view->revision);
        $this->check($view->complete && $view->forSummary()->facts === []);
        $this->failure(Failure::StaleRevision, fn () => $db->register($this->source('m1', 1, 'Deposit is 200.'), $view->revision));
        $this->register($db, $this->source('m1', 1, 'Deposit is 200.', 2));
        $view = $this->review($db, new SourceReference('m1', 2), ['Deposit is 200.']);
        $this->check($this->fact($view, 'Deposit is 200.')->proposition->id === $id);
    }

    private function contextEdit(): void
    {
        [$db] = $this->fixture();
        $question = $this->source('question', 1, 'Was the deposit returned?', actor: 'lawyer');
        $this->register($db, $question);
        $this->review($db, $question->reference, []);
        $reply = $this->source('reply', 2, 'No.');
        $this->register($db, $reply);
        $this->check($db->snapshot()->complete === false);
        $db->apply($this->plan($db, $reply->reference, [$this->draft('unpaid', 'Deposit remains unpaid.')],
            [new ClaimDraft('c', Reference::proposed('unpaid'), context: [$question->reference])]));
        $this->register($db, $this->source('question', 1, 'Were the documents received?', 2, 'lawyer'));
        $view = $db->snapshot();
        $this->check(count($view->pendingSources) === 2);
        $this->check($this->fact($view, 'Deposit remains unpaid.')->status === PropositionStatus::Unsupported);
        $bad = $this->plan($db, $reply->reference, [$this->draft('bad', 'Old context.')],
            [new ClaimDraft('c', Reference::proposed('bad'), context: [$question->reference])]);
        $this->failure(Failure::InvalidReference, fn () => $db->apply($bad));
        $this->review($db, new SourceReference('question', 2), []);
        $view = $db->apply($this->plan($db, $reply->reference, [$this->draft('missing', 'Documents were not received.')],
            [new ClaimDraft('c', Reference::proposed('missing'), context: [new SourceReference('question', 2)])]));
        $this->check($view->complete && count($view->forSummary()->facts) === 1);
    }

    private function transitiveImpact(): void
    {
        [$db] = $this->fixture();
        $previous = null;
        foreach (['A', 'B', 'C'] as $index => $text) {
            $source = $this->source('m' . $index, $index + 1, $text);
            $this->register($db, $source);
            $view = $db->apply($this->plan($db, $source->reference, [$this->draft('p', $text)],
                [new ClaimDraft('c', Reference::proposed('p'), dependsOn: $previous === null ? [] : [Reference::existing($previous)])]));
            $previous = $this->fact($view, $text)->claims[0]->id;
        }
        $this->register($db, $this->source('m0', 1, 'A changed.', 2));
        $this->check(count($db->snapshot()->pendingSources) === 3);
    }

    private function conflictingActors(): void
    {
        [$db] = $this->fixture();
        $first = $this->source('m1', 1, 'Rent is 12.', actor: 'client');
        $this->register($db, $first);
        $view = $this->review($db, $first->reference, ['Rent is 12.']);
        $target = $this->fact($view, 'Rent is 12.')->claims[0]->id;
        $other = $this->source('m2', 2, 'Rent is 15.', actor: 'landlord');
        $this->register($db, $other);
        $bad = $this->plan($db, $other->reference, [$this->draft('p', 'Rent is 15.')],
            [new ClaimDraft('c', Reference::proposed('p'), supersedes: [Reference::existing($target)])]);
        $this->failure(Failure::InvalidPlan, fn () => $db->apply($bad));
        $view = $db->apply($this->plan($db, $other->reference, [$this->draft('p', 'Rent is 15.')],
            [new ClaimDraft('c', Reference::proposed('p'), contradicts: [Reference::existing($target)])]));
        $this->check($view->complete && count($view->forSummary()->facts) === 2);
        $summaryInput = $view->forSummary();
        $this->check(array_map(static fn ($evidence): string => $evidence->actor, $summaryInput->evidence) === ['client', 'landlord']);
        $this->check(!property_exists($summaryInput->evidence[0], 'text'));
        foreach ($view->facts as $fact) {
            $this->check($fact->status === PropositionStatus::Disputed);
        }
        $this->check($view->sources['m1']->actor === 'client' && $view->sources['m2']->actor === 'landlord');
    }

    private function invalidPlans(): void
    {
        [$db] = $this->fixture();
        $source = $this->source('m1', 1, 'Two propositions.');
        $this->register($db, $source);
        $this->failure(Failure::InvalidReference, fn () => $db->apply($this->plan($db, $source->reference, [], [new ClaimDraft('c', Reference::existing('absent'))])));
        $this->failure(Failure::InvalidPlan, fn () => $db->apply($this->plan($db, $source->reference, [$this->draft('unused', 'Unused.')], [])));
        $drafts = [$this->draft('a', 'A.'), $this->draft('b', 'B.')];
        $claims = [new ClaimDraft('a', Reference::proposed('a'), dependsOn: [Reference::proposed('b')]),
            new ClaimDraft('b', Reference::proposed('b'), dependsOn: [Reference::proposed('a')])];
        $this->failure(Failure::InvalidPlan, fn () => $db->apply($this->plan($db, $source->reference, $drafts, $claims)));
        $self = [new ClaimDraft('a', Reference::proposed('a'), dependsOn: [Reference::proposed('a')])];
        $this->failure(Failure::InvalidReference, fn () => $db->apply($this->plan($db, $source->reference, [$this->draft('a', 'A.')], $self)));
        $this->check(count($db->snapshot()->facts) === 0);
    }

    private function supersededDependency(): void
    {
        [$db] = $this->fixture();
        $one = $this->source('m1', 1, 'Original.');
        $this->register($db, $one);
        $view = $this->review($db, $one->reference, ['Original.']);
        $first = $this->fact($view, 'Original.')->claims[0]->id;
        $two = $this->source('m2', 2, 'Dependent.');
        $this->register($db, $two);
        $db->apply($this->plan($db, $two->reference, [$this->draft('p', 'Dependent.')],
            [new ClaimDraft('c', Reference::proposed('p'), dependsOn: [Reference::existing($first)])]));
        $three = $this->source('m3', 3, 'Correction.');
        $this->register($db, $three);
        $view = $db->apply($this->plan($db, $three->reference, [$this->draft('p', 'Correction.')],
            [new ClaimDraft('c', Reference::proposed('p'), supersedes: [Reference::existing($first)])]));
        $this->check(count($view->pendingSources) === 1 && $view->pendingSources[0]->id === 'm2');
        $this->check($this->fact($view, 'Dependent.')->status === PropositionStatus::Unsupported);
    }

    private function reanalysis(): void
    {
        [$db] = $this->fixture();
        $source = $this->source('m1', 1, 'Durable proposition.');
        $this->register($db, $source);
        $view = $this->review($db, $source->reference, ['Durable proposition.']);
        $proposition = $this->fact($view, 'Durable proposition.')->proposition->id;
        $oldClaim = $this->fact($view, 'Durable proposition.')->claims[0]->id;
        $view = $db->apply($this->plan($db, $source->reference, [], [new ClaimDraft('new', Reference::existing($proposition))]));
        $this->check(count($view->facts) === 1 && !isset($view->claims[$oldClaim]));
        $this->check($view->facts[$proposition]->claims[0]->id !== $oldClaim);
    }

    private function scopeIsolation(): void
    {
        $pdo = $this->connection();
        foreach ([new Scope('tenant-a', 'same'), new Scope('tenant-b', 'same'), new Scope('tenant-a', 'other')] as $index => $scope) {
            [$db] = $this->fixture(pdo: $pdo, scope: $scope);
            $source = $this->source('same-id', 1, 'Private ' . $index);
            $this->register($db, $source);
            $view = $this->review($db, $source->reference, ['Private ' . $index]);
            $this->check(count($view->facts) === 1 && $view->facts['id:1']->proposition->statement === 'Private ' . $index);
        }
        [$other] = $this->fixture(pdo: $pdo, scope: new Scope('tenant-c', 'same'));
        $source = $this->source('same-id', 1, 'Unrelated.');
        $this->register($other, $source);
        $this->failure(Failure::InvalidReference, fn () => $other->apply($this->plan($other, $source->reference, [], [new ClaimDraft('c', Reference::existing('id:1'))])));
        $this->check(count($other->snapshot()->facts) === 0);
        $this->register($other, $this->source('tenant-c-only', 2, 'Private source.'));
        try {
            $pdo->exec("INSERT INTO factdb_evidence (tenant_id, scope_id, claim_id, source_id, source_version, role) VALUES ('tenant-a', 'same', 'id:2', 'tenant-c-only', 1, 'context')");
            throw new RuntimeException('Cross-tenant evidence passed a durable foreign key.');
        } catch (\PDOException) {
        }
    }

    private function restart(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'factdb-');
        try {
            $pdo = $this->connection('sqlite:' . $file);
            [$db] = $this->fixture(pdo: $pdo);
            $source = $this->source('m1', 1, 'Persistent.');
            $this->register($db, $source);
            $view = $this->review($db, $source->reference, ['Persistent.']);
            unset($db, $pdo);
            [$restarted] = $this->fixture(pdo: $this->connection('sqlite:' . $file));
            $after = $restarted->snapshot();
            $this->check($view == $after && $after->complete);
        } finally {
            unset($restarted);
            if (is_file($file)) { unlink($file); }
        }
    }

    private function concurrentSnapshots(): void
    {
        [, , $repository] = $this->fixture();
        $scope = new Scope('tenant', 'case');
        $one = $repository->load($scope);
        $two = $repository->load($scope);
        $repository->commit($one->register($this->source('winner', 1, 'Winner.'), 0), 0);
        $this->failure(Failure::StaleRevision, fn () => $repository->commit($two->register($this->source('loser', 2, 'Loser.'), 0), 0));
        $this->check(count($repository->load($scope)->sources) === 1);
    }

    private function analyzerBoundary(): void
    {
        [$db, $pdo] = $this->fixture();
        $source = $this->source('m1', 1, 'Analyze.');
        $this->register($db, $source);
        $analyzer = new class($db, $pdo) implements SourceAnalyzer {
            public function __construct(private FactDb $db, private PDO $pdo) {}
            public function analyze(SourceVersion $source, KnowledgeSnapshot $snapshot): ReconciliationPlan
            {
                if ($this->pdo->inTransaction()) { throw new RuntimeException('Analyzer was called inside a transaction.'); }
                $this->db->register(new SourceVersion(new SourceReference('m2', 1), 2, 'client', 'Concurrent input.', $source->occurredAt), $snapshot->revision);
                return new ReconciliationPlan('analyze', $snapshot->revision, $source->reference, 'qualified:fixture', [], []);
            }
        };
        $this->failure(Failure::StaleRevision, fn () => $db->reconcile('m1', $analyzer));
        $this->check(count($db->snapshot()->facts) === 0 && count($db->snapshot()->pendingSources) === 2);
    }

    private function rollback(): void
    {
        [$db, $pdo] = $this->fixture();
        $source = $this->source('m1', 1, 'Rollback.');
        $this->register($db, $source);
        $before = $db->snapshot();
        $pdo->exec("CREATE TRIGGER fail_claim BEFORE INSERT ON factdb_claims BEGIN SELECT RAISE(ABORT, 'injected failure'); END");
        $this->failure(Failure::Schema, fn () => $this->review($db, $source->reference, ['Must not partially commit.']));
        $this->check($db->snapshot() == $before);
        $this->check((int) $pdo->query('SELECT COUNT(*) FROM factdb_propositions')->fetchColumn() === 0);
        $this->check((int) $pdo->query('SELECT COUNT(*) FROM factdb_operations')->fetchColumn() === 0);
    }

    private function durableImmutability(): void
    {
        [$db, $pdo, $repository] = $this->fixture();
        $source = $this->source('m1', 1, 'Immutable.');
        $this->register($db, $source);
        $this->review($db, $source->reference, ['Immutable.']);
        foreach (['sources', 'propositions', 'claims', 'operations'] as $table) {
            try {
                $pdo->exec('UPDATE factdb_' . $table . " SET record_hash = 'changed'");
                throw new RuntimeException('Durable immutability guard did not fire.');
            } catch (\PDOException) {
            }
        }
        $before = $repository->load(new Scope('tenant', 'case'));
        $props = $before->propositions;
        $id = array_key_first($props);
        $props[$id] = new Proposition($id, $this->content('Different meaning.'), $props[$id]->createdAt);
        $forged = new KnowledgeState($before->scope, $before->revision + 1, false, $before->sources, $before->heads,
            $props, $before->claims, $before->analyses, $before->operations, $before->limits);
        $this->failure(Failure::ImmutableRecord, fn () => $repository->commit($forged, $before->revision));
        $this->check($repository->load($before->scope)->revision === $before->revision);
    }

    private function schemaOwnership(): void
    {
        $pdo = $this->connection();
        $this->failure(Failure::Schema, fn () => new SqliteRepository($pdo));
        $this->check((int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name LIKE 'factdb_%'")->fetchColumn() === 0);
        SqliteSchema::install($pdo);
        SqliteSchema::install($pdo);
        $this->check((int) $pdo->query('SELECT COUNT(*) FROM factdb_migrations')->fetchColumn() === 2);
        $pdo->exec("UPDATE factdb_migrations SET checksum = 'changed'");
        $this->failure(Failure::Schema, fn () => new SqliteRepository($pdo));
        $withoutForeignKeys = new PDO('sqlite::memory:');
        $this->failure(Failure::Schema, fn () => SqliteSchema::install($withoutForeignKeys));
        $future = $this->connection();
        $future->exec('CREATE TABLE factdb_migrations (version INTEGER PRIMARY KEY, checksum TEXT NOT NULL)');
        $future->exec("INSERT INTO factdb_migrations VALUES (3, 'future')");
        $this->failure(Failure::Schema, fn () => SqliteSchema::install($future));
        $this->check((int) $future->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'factdb_scopes'")->fetchColumn() === 0);
    }

    private function capacity(): void
    {
        [$db] = $this->fixture(new Limits(propositions: 1));
        $source = $this->source('m1', 1, 'Two facts.');
        $this->register($db, $source);
        $before = $db->snapshot();
        $this->failure(Failure::Capacity, fn () => $this->review($db, $source->reference, ['A.', 'B.']));
        $this->check($db->snapshot() == $before);
        [$small] = $this->fixture(new Limits(sourceBytes: 4));
        $this->failure(Failure::Capacity, fn () => $small->register($this->source('m1', 1, 'Too long.'), 0));
        $this->check($small->snapshot()->revision === 0);
    }

    private function erasure(): void
    {
        [$db, $pdo, $repository] = $this->fixture();
        $source = $this->source('m1', 1, 'Private source.');
        $this->register($db, $source);
        $this->review($db, $source->reference, ['Private proposition.']);
        $before = $repository->load(new Scope('tenant', 'case'));
        [$other] = $this->fixture(pdo: $pdo, scope: new Scope('other', 'case'));
        $this->register($other, $this->source('m1', 1, 'Other tenant.'));
        $this->review($other, new SourceReference('m1', 1), ['Other tenant.']);
        $db->erase($before->revision);
        foreach (['sources', 'heads', 'propositions', 'claims', 'evidence', 'links', 'analyses', 'operations'] as $table) {
            $this->check((int) $pdo->query('SELECT COUNT(*) FROM factdb_' . $table . " WHERE tenant_id = 'tenant'")->fetchColumn() === 0);
        }
        $this->failure(Failure::Erased, fn () => $db->snapshot());
        $this->failure(Failure::Erased, fn () => $db->register($source, 0));
        $this->failure(Failure::StaleRevision, fn () => $repository->commit($before->register($this->source('late', 2, 'Late.'), $before->revision), $before->revision));
        $db->erase(0);
        $this->check($repository->load($before->scope)->revision === $before->revision + 1);
        $this->check(count($other->snapshot()->forSummary()->facts) === 1);
    }

    private function checkedRecords(): void
    {
        $this->failure(Failure::InvalidInput, fn () => new Scope('../other', 'case'));
        $this->failure(Failure::InvalidInput, fn () => new SourceReference('m1', 0));
        $this->failure(Failure::InvalidInput, fn () => new Proposition('p1', ' ', '2026-10-18T00:00:00Z'));
        $this->failure(Failure::InvalidInput, fn () => new Proposition('p1', 'Fact.', '2026-02-30T00:00:00Z'));
        $this->failure(Failure::InvalidInput, fn () => new SourceReference('m1', 9007199254740992));
        $this->failure(Failure::Schema, fn () => RecordCodec::decode('propositions', '{"id":"p1","statement":"Injected.","createdAt":"2026-10-18T00:00:00Z","unexpected":true}'));
        $this->failure(Failure::Schema, fn () => RecordCodec::decode('sources', '{"reference":{"id":"m1","version":"1"},"position":1,"actor":"client","text":"Text.","occurredAt":"2026-10-18T00:00:00Z"}'));
        [$db] = $this->fixture();
        $empty = $this->source('empty', 1, '');
        $this->register($db, $empty);
        $view = $this->review($db, $empty->reference, []);
        $this->check($view->complete && $view->forSummary()->facts === []);
    }

    private function batchReconciliation(): void
    {
        [$db] = $this->fixture();
        $this->register($db, $this->source('m1', 1, 'Rent 10 and deposit 200.'));
        $this->register($db, $this->source('m2', 2, 'Correction rent 12, deposit 200 confirmed.'));
        $batch = new \FactDb\Model\ReconciliationBatch('batch:1', $db->snapshot()->revision, 'review:test', [
            $this->draft('rent', 'Rent is 10.'), $this->draft('deposit', 'Deposit is 200.'),
            $this->draft('corrected', 'Rent is 12.'),
        ], [
            new \FactDb\Model\SourceAnalysisDraft(new SourceReference('m1', 1), [
                new ClaimDraft('rent1', Reference::proposed('rent')), new ClaimDraft('deposit1', Reference::proposed('deposit'))]),
            new \FactDb\Model\SourceAnalysisDraft(new SourceReference('m2', 1), [
                new ClaimDraft('rent2', Reference::proposed('corrected'), supersedes: [Reference::proposed('rent1')]),
                new ClaimDraft('deposit2', Reference::proposed('deposit'))]),
        ]);
        $before = $db->snapshot();
        $db->validateBatch($batch);
        $this->check($before == $db->snapshot());
        $view = $db->applyBatch($batch);
        $this->check($view->complete && $view->revision === $batch->expectedRevision + 1);
        $this->check($this->fact($view, 'Rent is 10.')->status === PropositionStatus::Superseded);
        $this->check(count($this->fact($view, 'Deposit is 200.')->claims) === 2);
        $this->check($db->applyBatch($batch) == $view);
        $changed = new \FactDb\Model\ReconciliationBatch('batch:1', $batch->expectedRevision, 'review:changed', $batch->propositions, $batch->analyses);
        $this->failure(Failure::InvalidPlan, fn () => $db->applyBatch($changed));
    }

    private function batchRollback(): void
    {
        [$db] = $this->fixture();
        $this->register($db, $this->source('m1', 1, 'First.'));
        $this->register($db, $this->source('m2', 2, 'Invalid last.'));
        $before = $db->snapshot();
        $batch = new \FactDb\Model\ReconciliationBatch('batch:bad', $before->revision, 'review:test', [$this->draft('p', 'First.')], [
            new \FactDb\Model\SourceAnalysisDraft(new SourceReference('m1', 1), [new ClaimDraft('c', Reference::proposed('p'))]),
            new \FactDb\Model\SourceAnalysisDraft(new SourceReference('m2', 1), [new ClaimDraft('bad', Reference::existing('other-scope'))]),
        ]);
        $this->failure(Failure::InvalidReference, fn () => $db->applyBatch($batch));
        $this->check($db->snapshot() == $before, 'Invalid last source cannot commit the first source.');
    }

    private function analysisContract(): void
    {
        [$db] = $this->fixture();
        $this->register($db, $this->source('m1', 1, 'No, it is unpaid.'));
        $request = (new \FactDb\Analysis\AnalysisPrompt())->build($db->snapshot(), '{"language":"Persian"}', 24000);
        $json = '{"propositions":[{"key":"p","subject":"Deposit","predicate":"is unpaid.","object":null,"qualifiers":[]}],"analyses":[{"sourceId":"m1","claims":[{"key":"c","proposition":"p"}]}]}';
        $batch = $request->decode($json, 'analysis:1', 'review:test');
        $this->check($db->applyBatch($batch)->complete);
        foreach (['{}', str_replace('"sourceId":"m1"', '"sourceId":"other"', $json),
            str_replace('"key":"c"', '"key":"c","context":["other"]', $json),
            str_replace('"key":"c"', '"key":"c","extra":true', $json),
            str_replace('"key":"c"', '"key":"c","context":null', $json),
            str_replace('"key":"c"', '"key":"c","dependsOn":{}', $json)] as $bad) {
            $this->failure(Failure::InvalidPlan, fn () => $request->decode($bad, 'analysis:bad', 'review:test'));
        }
        $this->check(strlen($request->messages[0]['content']) < 7000, 'Static analysis contract has a regression budget.');
    }

    private function sourceSynchronization(): void
    {
        [$db] = $this->fixture();
        $one = $this->source('m1', 1, 'One.');
        $two = $this->source('m2', 2, 'Two.');
        $view = $db->synchronize([$one, $two], [], 0);
        $this->check($view->revision === 1 && count($view->sources) === 2);
        $this->check($db->synchronize([$one, $two], [], 1)->revision === 1);
        $this->failure(Failure::StaleRevision, fn () => $db->synchronize([$this->source('m1', 1, 'Edited.', 2), $this->source('m2', 2, 'Skipped version.', 3)], [], 1));
        $this->check($db->snapshot() == $view, 'Manifest failure rolls back all registrations.');
        $view = $db->synchronize([], [$one->reference], 1);
        $this->check($view->revision === 2 && count($view->sources) === 1);
    }

    private function summaryAttribution(): void
    {
        [$db] = $this->fixture();
        $this->register($db, $this->source('m1', 1, 'Rent 10.'));
        $first = $this->review($db, new SourceReference('m1', 1), ['Rent 10.']);
        $oldClaim = array_values($first->claims)[0]->id;
        $oldProposition = array_values($first->facts)[0]->proposition->id;
        $this->register($db, $this->source('m2', 2, 'Rent 10.', actor: 'other'));
        $this->review($db, new SourceReference('m2', 1), ['Rent 10.']);
        $this->register($db, $this->source('m3', 3, 'Correction rent 12.'));
        $view = $db->apply($this->plan($db, new SourceReference('m3', 1), [$this->draft('new', 'Rent 12.')],
            [new ClaimDraft('c', Reference::proposed('new'), supersedes: [Reference::existing($oldClaim)])]));
        $delivered = array_values(array_filter($view->forSummary()->facts, static fn ($fact): bool => $fact->proposition->id === $oldProposition))[0];
        $this->check(count($delivered->claims) === 1 && $delivered->claims[0]->source->id === 'm2');
    }

    private function analysisReferences(): void
    {
        [$db] = $this->fixture();
        $this->register($db, $this->source('message:1', 1, 'ودیعه دویست میلیون تومان است.'));
        $first = $this->review($db, new SourceReference('message:1', 1), ['ودیعه دویست میلیون تومان است.']);
        $amount = array_values($first->facts)[0]->proposition->id;
        $support = array_values($first->claims)[0]->id;
        $this->register($db, $this->source('message:2', 2, 'آیا موجر ودیعه را برگردانده است؟', actor: 'lawyer'));
        $this->review($db, new SourceReference('message:2', 1), ['وکیل درباره بازپرداخت ودیعه پرسیده است.']);
        $this->register($db, $this->source('message:3', 3, 'خیر، هنوز همان مبلغ را طلب دارم.'));
        $request = (new \FactDb\Analysis\AnalysisPrompt())->build($db->snapshot(), '{"language":"Persian"}', 24000);
        $input = json_decode($request->messages[1]['content'], true, 32, JSON_THROW_ON_ERROR);
        $this->check(array_column(array_column($input['sources'], 'reference'), 'id') === ['message:3']);
        $this->check(array_column(array_column($input['contextSources'], 'reference'), 'id') === ['message:1', 'message:2']);
        $this->check(count($input['claims']) === count($db->snapshot()->claims));
        $this->check($input['claims'][0]['sourceId'] === 'message:1' && $input['claims'][0]['actor'] === 'client');
        $amountAlias = array_column($input['propositions'], 'id', 'subject')['ودیعه'];
        $supportAlias = $input['claims'][0]['id'];
        $reply = ['propositions' => [['key' => 'p1', ...get_object_vars($this->content('ودیعه بازپرداخت نشده است.'))]], 'analyses' => [
            ['sourceId' => 'message:3', 'claims' => [
                ['key' => 'c1', 'proposition' => 'p1', 'context' => ['message:2']],
                ['key' => 'c2', 'proposition' => $amountAlias, 'context' => ['message:1'], 'dependsOn' => [$supportAlias]],
            ]],
        ]];
        $json = json_encode($reply, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $view = $db->applyBatch($request->decode($json, 'analysis:strings', 'review:test'));
        $this->check($view->complete && count($this->fact($view, 'ودیعه دویست میلیون تومان است.')->claims) === 2);
        $this->check($this->fact($view, 'ودیعه دویست میلیون تومان است.')->proposition->id === $amount);
        $this->check($this->fact($view, 'ودیعه دویست میلیون تومان است.')->claims[1]->dependsOn === [$support]);
        $this->check($this->fact($view, 'ودیعه بازپرداخت نشده است.')->claims[0]->context[0]->id === 'message:2');
        $reply['analyses'][0]['claims'][1]['dependsOn'] = ['other-scope-claim'];
        $this->failure(Failure::InvalidPlan, fn () => $request->decode(json_encode($reply), 'analysis:bad', 'review:test'));
        $reply['analyses'][0]['claims'][1]['dependsOn'] = [['kind' => 'existing', 'id' => $support]];
        $this->failure(Failure::InvalidPlan, fn () => $request->decode(json_encode($reply), 'analysis:bad', 'review:test'));
    }

    private function analysisAliases(): void
    {
        [, , $repository, $clock] = $this->fixture();
        $db = new FactDb(new Scope('tenant', 'case'), $repository, $clock, new class implements Identifiers {
            private int $counter = 0;
            public function next(): string { return (string) ++$this->counter; }
        });
        $this->register($db, $this->source('fact1', 1, 'Deposit is unpaid.'));
        $first = $this->review($db, new SourceReference('fact1', 1), ['Deposit is unpaid.']);
        $proposition = array_values($first->facts)[0]->proposition->id;
        $this->register($db, $this->source('claim1', 2, 'Deposit is unpaid.'));
        $request = (new \FactDb\Analysis\AnalysisPrompt())->build($db->snapshot(), '{}', 24000);
        $input = json_decode($request->messages[1]['content'], true, 32, JSON_THROW_ON_ERROR);
        $this->check($input['propositions'][0]['id'] === 'fact2' && $input['claims'][0]['id'] === 'claim2');
        $reply = ['propositions' => [], 'analyses' => [['sourceId' => 'claim1', 'claims' => [['key' => 'new', 'proposition' => 'fact2', 'dependsOn' => ['claim2']]]]]];
        $batch = $request->decode(json_encode($reply), 'aliases:valid', 'review:test');
        $this->check($batch->analyses[0]->claims[0]->proposition->id === $proposition);
        $this->check($batch->analyses[0]->claims[0]->dependsOn[0]->id === array_values($first->claims)[0]->id);
        $view = $db->applyBatch($batch);
        $this->check(count($view->facts) === 1 && count($view->facts[$proposition]->claims) === 2);
        foreach (['fact1', $proposition, 'unknown'] as $invalid) {
            $reply['analyses'][0]['claims'][0]['proposition'] = $invalid;
            $this->failure(Failure::InvalidPlan, fn () => $request->decode(json_encode($reply), 'aliases:bad', 'review:test'));
        }
        $reply['propositions'] = [['key' => 'fact2', ...get_object_vars($this->content('Another meaning.'))]];
        $reply['analyses'][0]['claims'][0]['proposition'] = 'fact2';
        $this->failure(Failure::InvalidPlan, fn () => $request->decode(json_encode($reply), 'aliases:collision', 'review:test'));
    }

    private function serialization(): void
    {
        [$db] = $this->fixture();
        $empty = json_decode(json_encode($db->snapshot(), JSON_THROW_ON_ERROR), false, 32, JSON_THROW_ON_ERROR);
        $this->check(is_object($empty->facts) && is_object($empty->sources) && is_object($empty->claims));
        $source = $this->source('0', 0, 'Numeric source ID.');
        $this->register($db, $source);
        $view = $this->review($db, $source->reference, ['Numeric source ID is valid.']);
        $json = json_decode(json_encode($view, JSON_THROW_ON_ERROR), false, 32, JSON_THROW_ON_ERROR);
        $this->check(is_object($json->sources) && $json->sources->{'0'}->reference->id === '0');
        $this->check(is_array($json->pendingSources) && is_array($json->supersededClaimIds));
        $input = json_decode(json_encode($view->forSummary(), JSON_THROW_ON_ERROR), false, 32, JSON_THROW_ON_ERROR);
        $this->check(is_array($input->facts) && is_array($input->evidence));
        $this->check($input->evidence[0]->reference->id === '0' && !property_exists($input->evidence[0], 'text'));
    }

    private function structuredContent(): void
    {
        $condition = new Qualifier('شرط', 'اگر قرارداد تمدید شود');
        $reason = new Qualifier('دلیل', 'مبلغ ودیعه بالاست');
        $content = new PropositionContent('قرارداد اجاره', 'منصفانه نیست', null, [$reason, $condition]);
        $this->check($content->identity() === (new PropositionContent('قرارداد اجاره', 'منصفانه نیست', null, [$condition, $reason]))->identity());
        $record = new Proposition('structured', $content, '2026-10-18T00:00:00Z');
        $json = RecordCodec::encode($record);
        $decoded = RecordCodec::decode('propositions', $json);
        $this->check($decoded == $record && !str_contains($json, 'statement') && !str_contains($json, 'polarity'));
        $this->check(str_contains($record->statement, 'منصفانه نیست') && str_contains($record->statement, 'اگر قرارداد تمدید شود'));
        $this->check($content->identity() !== (new PropositionContent('قرارداد اجاره', 'منصفانه نیست', null, [$reason]))->identity());
        $this->failure(Failure::InvalidInput, fn () => new PropositionContent('', 'است'));
        $this->failure(Failure::InvalidInput, fn () => new PropositionContent('ودیعه', 'است', ''));
        $this->failure(Failure::InvalidInput, fn () => new PropositionContent('ودیعه', 'است', null, [$reason, $reason]));
        $this->failure(Failure::Capacity, fn () => new PropositionContent('ودیعه', 'است', null, array_fill(0, 13, $reason)));
        [$db] = $this->fixture();
        $this->register($db, $this->source('m1', 1, 'اگر قرارداد تمدید شود، به دلیل مبلغ ودیعه آن را منصفانه نمی‌دانم.'));
        $request = (new \FactDb\Analysis\AnalysisPrompt())->build($db->snapshot(), '{}', 24000);
        $valid = ['propositions' => [['key' => 'p1', ...get_object_vars($content)]], 'analyses' => [
            ['sourceId' => 'm1', 'claims' => [['key' => 'c1', 'proposition' => 'p1']]],
        ]];
        $view = $db->applyBatch($request->decode(json_encode($valid), 'structured:one', 'review:test'));
        $this->check(count($view->forSummary()->facts) === 1 && count($view->claims) === 1, 'Neither a condition proposition nor a reason claim is required.');
        $this->check($view->forSummary()->evidence[0]->actor === 'client');
        foreach (['polarity' => 'negative', 'attribution' => 'forged', 'reasonClaimRefs' => ['c2']] as $key => $value) {
            $bad = $valid; $bad['propositions'][0][$key] = $value;
            $this->failure(Failure::InvalidPlan, fn () => $request->decode(json_encode($bad), 'bad', 'review:test'));
        }
        foreach ([['type' => 'condition', 'value' => ['propositionRef' => 'p3']], ['type' => 'condition', 'value' => null],
            ['type' => 'condition', 'value' => 'if renewed', 'extra' => true]] as $qualifier) {
            $bad = $valid; $bad['propositions'][0]['qualifiers'] = [$qualifier];
            $this->failure(Failure::InvalidPlan, fn () => $request->decode(json_encode($bad), 'bad', 'review:test'));
        }
        $bad = $valid; unset($bad['propositions'][0]['object']);
        $this->failure(Failure::InvalidPlan, fn () => $request->decode(json_encode($bad), 'bad', 'review:test'));
    }

    private function structuredCorrection(): void
    {
        [$db] = $this->fixture();
        $source = $this->source('m1', 1, 'مبلغ و تاریخ تمدید مشخص نیست.');
        $this->register($db, $source);
        $amount = new PropositionDraft('amount', new PropositionContent('مبلغ تمدید', 'مشخص نیست'));
        $date = new PropositionDraft('date', new PropositionContent('تاریخ تمدید', 'مشخص نیست'));
        $first = $db->apply($this->plan($db, $source->reference, [$amount, $date], [
            new ClaimDraft('a', Reference::proposed('amount')), new ClaimDraft('d', Reference::proposed('date')),
        ]));
        $dateId = $this->fact($first, 'تاریخ تمدید مشخص نیست')->proposition->id;
        $oldAmount = $this->fact($first, 'مبلغ تمدید مشخص نیست')->proposition->id;
        $this->register($db, $this->source('m1', 1, 'مبلغ تمدید دوازده میلیون تومان است و تاریخ آن مشخص نیست.', 2));
        $view = $db->apply($this->plan($db, new SourceReference('m1', 2), [new PropositionDraft('amount',
            new PropositionContent('مبلغ تمدید', 'است', 'دوازده میلیون تومان'))], [
            new ClaimDraft('a', Reference::proposed('amount')), new ClaimDraft('d', Reference::existing($dateId)),
        ]));
        $this->check(count($view->forSummary()->facts) === 2 && $view->facts[$oldAmount]->status === PropositionStatus::Unsupported);
        $this->check($view->facts[$dateId]->status === PropositionStatus::Supported && $view->facts[$dateId]->proposition == $first->facts[$dateId]->proposition);
    }

    private function legacyUpgrade(): void
    {
        $pdo = $this->connection();
        $file = dirname(__DIR__) . '/schema/001_initial.sql';
        $pdo->exec((string) file_get_contents($file));
        $pdo->exec('CREATE TABLE factdb_migrations (version INTEGER PRIMARY KEY, checksum TEXT NOT NULL)');
        $pdo->prepare('INSERT INTO factdb_migrations VALUES (1, ?)')->execute([hash_file('sha256', $file)]);
        $pdo->exec("INSERT INTO factdb_scopes VALUES ('tenant', 'case', 1, 0)");
        $source = $this->source('m1', 1, 'ودیعه دویست میلیون تومان است.');
        $legacy = new Proposition('legacy', 'ودیعه دویست میلیون تومان است.', '2026-10-18T00:00:00Z');
        $claim = new \FactDb\Model\Claim('old-claim', 'legacy', $source->reference, [], [], [], [], $legacy->createdAt);
        $sourceJson = RecordCodec::encode($source); $legacyJson = RecordCodec::encode($legacy); $claimJson = RecordCodec::encode($claim);
        $pdo->prepare("INSERT INTO factdb_sources VALUES ('tenant', 'case', 'm1', 1, ?, ?)")->execute([$sourceJson, hash('sha256', $sourceJson)]);
        $pdo->exec("INSERT INTO factdb_heads VALUES ('tenant', 'case', 'm1', 1, 1)");
        $pdo->prepare("INSERT INTO factdb_propositions VALUES ('tenant', 'case', 'legacy', ?, ?, ?)")->execute([hash('sha256', $legacy->statement), $legacyJson, hash('sha256', $legacyJson)]);
        $pdo->prepare("INSERT INTO factdb_claims VALUES ('tenant', 'case', 'old-claim', 'legacy', 'm1', 1, ?, ?)")->execute([$claimJson, hash('sha256', $claimJson)]);
        $pdo->exec("INSERT INTO factdb_evidence VALUES ('tenant', 'case', 'old-claim', 'm1', 1, 'support')");
        $analysis = new \FactDb\Model\SourceAnalysis($source->reference, ['old-claim'], 'old-review');
        $pdo->prepare("INSERT INTO factdb_analyses VALUES ('tenant', 'case', 'm1', 1, ?)")->execute([RecordCodec::encode($analysis)]);
        [$db] = $this->fixture(pdo: $pdo);
        $view = $db->snapshot();
        $this->check(!$view->complete && $view->pendingSources == [$source->reference]);
        $this->failure(Failure::Incomplete, fn () => $view->forSummary());
        $this->failure(Failure::InvalidPlan, fn () => $db->apply($this->plan($db, $source->reference, [], [new ClaimDraft('c', Reference::existing('legacy'))])));
        $request = (new \FactDb\Analysis\AnalysisPrompt())->build($view, '{}', 24000);
        $input = json_decode($request->messages[1]['content'], true);
        $this->check($input['propositions'] === [], 'Legacy prose cannot be reused as structured content.');
        $view = $db->apply($this->plan($db, $source->reference, [new PropositionDraft('p', new PropositionContent('ودیعه', 'است', 'دویست میلیون تومان'))],
            [new ClaimDraft('c', Reference::proposed('p'))]));
        $this->check($view->complete && count($view->facts) === 2 && count($view->forSummary()->facts) === 1);
        $this->check($pdo->query("SELECT record_json FROM factdb_propositions WHERE id = 'legacy'")->fetchColumn() === $legacyJson);
        $this->check($pdo->query("SELECT record_json FROM factdb_claims WHERE id = 'old-claim'")->fetchColumn() === $claimJson);
        $this->check((new SqliteRepository($pdo))->load(new Scope('tenant', 'case'))->snapshot() == $view);
    }

    private function hostCommitGuard():void
    {
        $pdo=$this->connection();SqliteSchema::install($pdo);$guardRan=false;
        $repository=new SqliteRepository($pdo,commitGuard:static function()use($pdo,&$guardRan):void {
            assert((int)$pdo->query('SELECT COUNT(*) FROM factdb_scopes')->fetchColumn()===1);
            $guardRan=true;throw new RuntimeException('Cancelled host attempt.');
        });
        try { $repository->commit(new KnowledgeState(new Scope('tenant','guard'),revision:1),0);assert(false); }
        catch(RuntimeException $error) { assert($error->getMessage()==='Cancelled host attempt.'); }
        assert($guardRan && (int)$pdo->query('SELECT COUNT(*) FROM factdb_scopes')->fetchColumn()===0);
    }

}
