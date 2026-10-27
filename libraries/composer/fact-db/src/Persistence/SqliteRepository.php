<?php

declare(strict_types=1);

namespace FactDb\Persistence;

use Closure;
use FactDb\FactDbException;
use FactDb\Failure;
use FactDb\Model\{AppliedOperation, Claim, KnowledgeState, Limits, Proposition, Scope, SourceAnalysis, SourceVersion};
use FactDb\Port\Repository;
use PDO;
use PDOException;
use Throwable;

final class SqliteRepository implements Repository
{
    /** @param Closure():void|null $commitGuard Host-owned fence checked inside each durable transaction. */
    public function __construct(private PDO $pdo, private Limits $limits = new Limits(), private ?Closure $commitGuard = null)
    {
        SqliteSchema::assertCurrent($pdo);
    }

    public function load(Scope $scope): KnowledgeState
    {
        $result = $this->transaction(function () use ($scope): KnowledgeState {
            $statement = $this->pdo->prepare('SELECT revision, erased FROM factdb_scopes WHERE tenant_id = ? AND scope_id = ?');
            $statement->execute([$scope->tenantId, $scope->scopeId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                return new KnowledgeState($scope, limits: $this->limits);
            }
            $records = [];
            $maximums = ['sources' => $this->limits->sourceVersions, 'propositions' => $this->limits->propositions,
                'claims' => $this->limits->claims, 'analyses' => $this->limits->sources, 'operations' => $this->limits->operations];
            foreach ($maximums as $kind => $maximum) {
                $count = $this->pdo->prepare('SELECT COUNT(*), COALESCE(SUM(length(CAST(record_json AS BLOB))), 0) FROM factdb_' . $kind . ' WHERE tenant_id = ? AND scope_id = ?');
                $count->execute([$scope->tenantId, $scope->scopeId]);
                $size = $count->fetch(PDO::FETCH_NUM);
                if ((int) $size[0] > $maximum || ($kind === 'sources' && (int) $size[1] > $this->limits->sourceBytes * 2 + $maximum * 2048)) {
                    throw new FactDbException(Failure::Capacity, 'Stored scope exceeds this adapter\'s aggregate capacity.');
                }
                $query = $this->pdo->prepare('SELECT * FROM factdb_' . $kind . ' WHERE tenant_id = ? AND scope_id = ?');
                $query->execute([$scope->tenantId, $scope->scopeId]);
                $records[$kind] = [];
                while ($record = $query->fetch(PDO::FETCH_ASSOC)) {
                    if (isset($record['record_hash']) && !hash_equals($record['record_hash'], hash('sha256', $record['record_json']))) {
                        throw new FactDbException(Failure::Schema, 'Stored immutable record failed its integrity check.');
                    }
                    $decoded = RecordCodec::decode($kind, $record['record_json']);
                    $matches = match (true) {
                        $decoded instanceof SourceVersion => $decoded->reference->id === $record['source_id'] && $decoded->reference->version === (int) $record['version'],
                        $decoded instanceof Proposition => $decoded->id === $record['id'] && hash_equals($record['content_hash'], hash('sha256', $decoded->identity())),
                        $decoded instanceof Claim => $decoded->id === $record['id'] && $decoded->propositionId === $record['proposition_id']
                            && $decoded->source->id === $record['source_id'] && $decoded->source->version === (int) $record['source_version'],
                        $decoded instanceof SourceAnalysis => $decoded->source->id === $record['source_id'] && $decoded->source->version === (int) $record['source_version'],
                        default => $decoded->id === $record['id'],
                    };
                    if (!$matches) {
                        throw new FactDbException(Failure::Schema, 'Stored record identity or relational projection is inconsistent.');
                    }
                    $key = match (true) {
                        $decoded instanceof SourceVersion => $decoded->reference->key(),
                        $decoded instanceof SourceAnalysis => $decoded->source->id,
                        default => $decoded->id,
                    };
                    $records[$kind][$key] = $decoded;
                }
            }
            $query = $this->pdo->prepare('SELECT source_id, version FROM factdb_heads WHERE tenant_id = ? AND scope_id = ? LIMIT ?');
            $query->bindValue(1, $scope->tenantId);
            $query->bindValue(2, $scope->scopeId);
            $query->bindValue(3, $this->limits->sources + 1, PDO::PARAM_INT);
            $query->execute();
            $heads = [];
            while ($head = $query->fetch(PDO::FETCH_ASSOC)) {
                $heads[$head['source_id']] = $head['version'] === null ? null : (int) $head['version'];
            }
            return new KnowledgeState($scope, (int) $row['revision'], (bool) $row['erased'],
                $records['sources'], $heads, $records['propositions'], $records['claims'], $records['analyses'], $records['operations'], $this->limits);
        });
        if (!$result instanceof KnowledgeState) {
            throw new FactDbException(Failure::Schema, 'Repository returned no consistent snapshot.');
        }
        return $result;
    }

    public function commit(KnowledgeState $state, int $expectedRevision): void
    {
        if ($state->limits != $this->limits) {
            $state = new KnowledgeState($state->scope, $state->revision, $state->erased, $state->sources, $state->heads,
                $state->propositions, $state->claims, $state->analyses, $state->operations, $this->limits);
        }
        if ($state->erased || $state->revision !== $expectedRevision + 1) {
            throw new FactDbException(Failure::StaleRevision, 'A commit must advance one live scope revision.');
        }
        $this->transaction(function () use ($state, $expectedRevision): void {
            $scope = $state->scope;
            $this->advance($scope, $expectedRevision);
            $positions = [];
            foreach ($state->sources as $source) {
                $this->append('sources', $scope, ['source_id' => $source->reference->id, 'version' => $source->reference->version], $source);
                $positions[$source->reference->id] = $source->position;
            }
            foreach ($state->propositions as $proposition) {
                $this->append('propositions', $scope, ['id' => $proposition->id], $proposition,
                    ['content_hash' => hash('sha256', $proposition->identity())]);
            }
            $newClaims = [];
            foreach ($state->claims as $claim) {
                if ($this->append('claims', $scope, ['id' => $claim->id], $claim, [
                    'proposition_id' => $claim->propositionId, 'source_id' => $claim->source->id, 'source_version' => $claim->source->version,
                ])) {
                    $newClaims[] = $claim;
                }
            }
            $evidence = $this->pdo->prepare('INSERT INTO factdb_evidence (tenant_id, scope_id, claim_id, source_id, source_version, role) VALUES (?, ?, ?, ?, ?, ?)');
            $link = $this->pdo->prepare('INSERT INTO factdb_links (tenant_id, scope_id, from_claim, to_claim, kind) VALUES (?, ?, ?, ?, ?)');
            foreach ($newClaims as $claim) {
                $evidence->execute([$scope->tenantId, $scope->scopeId, $claim->id, $claim->source->id, $claim->source->version, 'support']);
                foreach ($claim->context as $reference) {
                    $evidence->execute([$scope->tenantId, $scope->scopeId, $claim->id, $reference->id, $reference->version, 'context']);
                }
                foreach (['depends_on' => $claim->dependsOn, 'supersedes' => $claim->supersedes, 'contradicts' => $claim->contradicts] as $kind => $targets) {
                    foreach ($targets as $target) {
                        $link->execute([$scope->tenantId, $scope->scopeId, $claim->id, $target, $kind]);
                    }
                }
            }
            $head = $this->pdo->prepare('INSERT INTO factdb_heads (tenant_id, scope_id, source_id, version, position) VALUES (?, ?, ?, ?, ?) ON CONFLICT (tenant_id, scope_id, source_id) DO UPDATE SET version = excluded.version, position = excluded.position');
            foreach ($state->heads as $sourceId => $version) {
                $head->execute([$scope->tenantId, $scope->scopeId, (string) $sourceId, $version, $positions[$sourceId]]);
            }
            $analysis = $this->pdo->prepare('INSERT INTO factdb_analyses (tenant_id, scope_id, source_id, source_version, record_json) VALUES (?, ?, ?, ?, ?) ON CONFLICT (tenant_id, scope_id, source_id) DO UPDATE SET source_version = excluded.source_version, record_json = excluded.record_json');
            foreach ($state->analyses as $value) {
                $analysis->execute([$scope->tenantId, $scope->scopeId, $value->source->id, $value->source->version, RecordCodec::encode($value)]);
            }
            foreach ($state->operations as $operation) {
                $this->append('operations', $scope, ['id' => $operation->id], $operation);
            }
        });
    }

    public function erase(Scope $scope, int $expectedRevision): void
    {
        $this->transaction(function () use ($scope, $expectedRevision): void {
            $statement = $this->pdo->prepare('SELECT erased FROM factdb_scopes WHERE tenant_id = ? AND scope_id = ?');
            $statement->execute([$scope->tenantId, $scope->scopeId]);
            if ((int) $statement->fetchColumn() === 1) {
                return;
            }
            $this->advance($scope, $expectedRevision);
            $delete = $this->pdo->prepare('DELETE FROM factdb_scopes WHERE tenant_id = ? AND scope_id = ?');
            $delete->execute([$scope->tenantId, $scope->scopeId]);
            $tombstone = $this->pdo->prepare('INSERT INTO factdb_scopes (tenant_id, scope_id, revision, erased) VALUES (?, ?, ?, 1)');
            $tombstone->execute([$scope->tenantId, $scope->scopeId, $expectedRevision + 1]);
        });
    }

    /** @param array<string, int|string> $identity @param array<string, int|string> $extra */
    private function append(string $table, Scope $scope, array $identity,
        SourceVersion|Proposition|Claim|AppliedOperation $record, array $extra = []): bool
    {
        $json = RecordCodec::encode($record);
        $values = ['tenant_id' => $scope->tenantId, 'scope_id' => $scope->scopeId, ...$identity, ...$extra,
            'record_json' => $json, 'record_hash' => hash('sha256', $json)];
        $columns = array_keys($values);
        $conflict = ['tenant_id', 'scope_id', ...array_keys($identity)];
        $sql = 'INSERT INTO factdb_' . $table . ' (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($values), '?')) . ') ON CONFLICT (' . implode(', ', $conflict)
            . ') DO UPDATE SET record_hash = excluded.record_hash WHERE record_hash <> excluded.record_hash';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_values($values));
        return $statement->rowCount() === 1;
    }

    private function advance(Scope $scope, int $expectedRevision): void
    {
        \FactDb\Model\Guard::integer($expectedRevision);
        \FactDb\Model\Guard::integer($expectedRevision + 1);
        $initial = $this->pdo->prepare('INSERT INTO factdb_scopes (tenant_id, scope_id, revision, erased) VALUES (?, ?, 0, 0) ON CONFLICT DO NOTHING');
        $initial->execute([$scope->tenantId, $scope->scopeId]);
        $update = $this->pdo->prepare('UPDATE factdb_scopes SET revision = revision + 1 WHERE tenant_id = ? AND scope_id = ? AND revision = ? AND erased = 0');
        $update->execute([$scope->tenantId, $scope->scopeId, $expectedRevision]);
        if ($update->rowCount() !== 1) {
            throw new FactDbException(Failure::StaleRevision, 'Knowledge changed or the scope was erased.');
        }
    }

    /** @param Closure(): (KnowledgeState|null) $work */
    private function transaction(Closure $work): ?KnowledgeState
    {
        if ($this->pdo->inTransaction() || (int) $this->pdo->query('PRAGMA foreign_keys')->fetchColumn() !== 1) {
            throw new FactDbException(Failure::Schema, 'FactDB requires its own transaction with foreign keys enabled.');
        }
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            ($this->commitGuard)?->__invoke();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($error instanceof PDOException) {
                $reason = str_contains($error->getMessage(), 'factdb immutable') ? Failure::ImmutableRecord : Failure::Schema;
                throw new FactDbException($reason, 'FactDB durable transaction failed.', $error);
            }
            throw $error;
        }
    }
}
