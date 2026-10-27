<?php

declare(strict_types=1);

namespace FactDb\Model;

use FactDb\FactDbException;
use FactDb\Failure;
use FactDb\Port\Identifiers;

final readonly class KnowledgeState
{
    /**
     * @param array<string, SourceVersion> $sources Keyed by SourceReference::key().
     * @param array<string, int|null> $heads Null is a withdrawn source.
     * @param array<string, Proposition> $propositions
     * @param array<string, Claim> $claims
     * @param array<string, SourceAnalysis> $analyses Keyed by source ID.
     * @param array<string, AppliedOperation> $operations
     */
    public function __construct(
        public Scope $scope,
        public int $revision = 0,
        public bool $erased = false,
        public array $sources = [],
        public array $heads = [],
        public array $propositions = [],
        public array $claims = [],
        public array $analyses = [],
        public array $operations = [],
        public Limits $limits = new Limits()
    ) {
        Guard::integer($revision);
        Guard::objects(array_values($sources), SourceVersion::class, $limits->sourceVersions);
        Guard::objects(array_values($propositions), Proposition::class, $limits->propositions);
        Guard::objects(array_values($claims), Claim::class, $limits->claims);
        Guard::objects(array_values($analyses), SourceAnalysis::class, $limits->sources);
        Guard::objects(array_values($operations), AppliedOperation::class, $limits->operations);
        if (array_sum(array_map(static fn (SourceVersion $source): int => strlen($source->text), $sources)) > $limits->sourceBytes) {
            throw new FactDbException(Failure::Capacity, 'Retained source text exceeds the aggregate byte budget.');
        }
        if (count($heads) > $limits->sources) {
            throw new FactDbException(Failure::Capacity, 'Source capacity exceeded.');
        }
        if ($erased && ($sources !== [] || $heads !== [] || $propositions !== [] || $claims !== [] || $analyses !== [] || $operations !== [])) {
            throw new FactDbException(Failure::InvalidInput, 'An erased scope cannot retain knowledge.');
        }
        $this->validateRecords();
    }

    public function register(SourceVersion $source, int $expectedRevision): self
    {
        $this->assertAvailable();
        $reference = $source->reference;
        $previous = $this->sources[$reference->key()] ?? null;
        if ($previous !== null) {
            if ($previous != $source) {
                throw new FactDbException(Failure::ImmutableRecord, 'A source version cannot change meaning or content.');
            }
            if (($this->heads[$reference->id] ?? null) === $reference->version) {
                return $this;
            }
            throw new FactDbException(Failure::StaleRevision, 'An old source version cannot become current again.');
        }
        $this->assertRevision($expectedRevision);
        $latest = null;
        foreach ($this->sources as $item) {
            if ($item->reference->id === $reference->id) {
                if ($item->position !== $source->position || $item->occurredAt !== $source->occurredAt) {
                    throw new FactDbException(Failure::InvalidInput, 'An edit must preserve original source chronology.');
                }
                if ($latest === null || $item->reference->version > $latest->reference->version) {
                    $latest = $item;
                }
            } elseif ($item->position === $source->position) {
                throw new FactDbException(Failure::InvalidInput, 'Source positions must be unique within a scope.');
            }
        }
        if ($reference->version !== ($latest?->reference->version ?? 0) + 1) {
            throw new FactDbException(Failure::StaleRevision, 'Source versions must advance by one.');
        }
        return $this->next(
            sources: [...$this->sources, $reference->key() => $source],
            heads: array_replace($this->heads, [$reference->id => $reference->version])
        );
    }

    public function withdraw(string $sourceId, int $version, int $expectedRevision): self
    {
        $this->assertAvailable();
        Guard::id($sourceId);
        Guard::integer($version, 1);
        if (!isset($this->sources[(new SourceReference($sourceId, $version))->key()])) {
            throw new FactDbException(Failure::InvalidReference, 'Unknown source version.');
        }
        $head = $this->heads[$sourceId] ?? null;
        if ($head === null) {
            $latest = max(array_map(static fn (SourceVersion $source): int =>
                $source->reference->id === $sourceId ? $source->reference->version : 0, $this->sources));
            if ($latest === $version) {
                return $this;
            }
        }
        if ($head !== $version) {
            throw new FactDbException(Failure::StaleRevision, 'Withdrawal targets a stale source version.');
        }
        $this->assertRevision($expectedRevision);
        return $this->next(heads: array_replace($this->heads, [$sourceId => null]));
    }

    public function apply(ReconciliationPlan $plan, Identifiers $identifiers, string $now): self
    {
        $this->assertAvailable();
        $receipt = $this->operations[$plan->operationId] ?? null;
        if ($receipt !== null) {
            if ($receipt->fingerprint !== $plan->fingerprint()) {
                throw new FactDbException(Failure::InvalidPlan, 'Operation identity was reused for a different plan.');
            }
            return $this;
        }
        $this->assertRevision($plan->expectedRevision);
        $this->assertCurrent($plan->source);
        $snapshot = $this->snapshot();
        $propositions = $this->propositions;
        $propositionIds = [];
        foreach ($plan->propositions as $draft) {
            if (isset($propositionIds[$draft->key])) {
                throw new FactDbException(Failure::InvalidPlan, 'Duplicate proposition draft key.');
            }
            $existing = null;
            foreach ($propositions as $proposition) {
                if ($proposition->content !== null && $proposition->identity() === $draft->content->identity()) {
                    $existing = $proposition->id;
                    break;
                }
            }
            $id = $existing ?? $identifiers->next();
            if ($existing === null) {
                if (isset($propositions[$id])) {
                    throw new FactDbException(Failure::InvalidPlan, 'Generated proposition ID already exists.');
                }
                $propositions[$id] = new Proposition($id, $draft->content, $now);
            }
            $propositionIds[$draft->key] = $id;
        }
        $claimIds = [];
        foreach ($plan->claims as $draft) {
            $id = $identifiers->next();
            if (isset($claimIds[$draft->key]) || isset($this->claims[$id]) || in_array($id, $claimIds, true)) {
                throw new FactDbException(Failure::InvalidPlan, 'Claim keys and generated IDs must be unique.');
            }
            Guard::id($id);
            $claimIds[$draft->key] = $id;
        }
        $resolve = static function (Reference $reference, array $proposed, array $existing): string {
            $id = $reference->kind === ReferenceKind::Existing ? $reference->id : ($proposed[$reference->id] ?? null);
            if ($id === null || ($reference->kind === ReferenceKind::Existing && !isset($existing[$id]))) {
                throw new FactDbException(Failure::InvalidReference, 'Unknown, stale, or cross-scope reference.');
            }
            return $id;
        };
        $claims = $this->claims;
        $usedPropositions = [];
        $seen = [];
        foreach ($plan->claims as $draft) {
            $propositionId = $resolve($draft->proposition, $propositionIds, $this->propositions);
            if ($propositions[$propositionId]->content === null) {
                throw new FactDbException(Failure::InvalidPlan, 'Legacy prose requires fresh structured extraction.');
            }
            if (isset($seen[$propositionId])) {
                throw new FactDbException(Failure::InvalidPlan, 'One source analysis cannot repeat the same proposition.');
            }
            $seen[$propositionId] = true;
            if ($draft->proposition->kind === ReferenceKind::Proposed) {
                $usedPropositions[$draft->proposition->id] = true;
            }
            foreach ($draft->context as $reference) {
                $this->assertCurrent($reference);
            }
            $references = function (array $values) use ($resolve, $claimIds, $snapshot, $plan): array {
                return array_map(function (Reference $reference) use ($resolve, $claimIds, $snapshot, $plan): string {
                    $id = $resolve($reference, $claimIds, $snapshot->claims);
                    if ($reference->kind === ReferenceKind::Existing
                        && $snapshot->claims[$id]->source->id === $plan->source->id) {
                        throw new FactDbException(Failure::InvalidReference, 'A replaced analysis cannot supply its own old claims.');
                    }
                    return $id;
                }, $values);
            };
            $dependsOn = $references($draft->dependsOn);
            foreach ($dependsOn as $id) {
                if (in_array($id, $snapshot->supersededClaimIds, true) || in_array($id, $snapshot->disputedClaimIds, true)) {
                    throw new FactDbException(Failure::InvalidReference, 'A dependency is superseded or disputed.');
                }
            }
            $claim = new Claim($claimIds[$draft->key], $propositionId, $plan->source, $draft->context,
                $dependsOn, $references($draft->supersedes), $references($draft->contradicts), $now);
            $claims[$claim->id] = $claim;
        }
        if (count($usedPropositions) !== count($propositionIds)) {
            throw new FactDbException(Failure::InvalidPlan, 'A proposed proposition has no supporting claim.');
        }
        return $this->next(
            propositions: $propositions,
            claims: $claims,
            analyses: array_replace($this->analyses, [$plan->source->id => new SourceAnalysis($plan->source, array_values($claimIds), $plan->reviewId)]),
            operations: array_replace($this->operations, [$plan->operationId => new AppliedOperation($plan->operationId, $plan->fingerprint(), $this->revision + 1)])
        );
    }

    /** Preflight the complete batch in memory; publish all sources in one repository CAS. */
    public function applyBatch(ReconciliationBatch $batch, Identifiers $identifiers, string $now): self
    {
        $this->assertAvailable();
        $receipt = $this->operations[$batch->operationId] ?? null;
        if ($receipt !== null) {
            if ($receipt->fingerprint !== $batch->fingerprint()) {
                throw new FactDbException(Failure::InvalidPlan, 'Batch identity was reused for different content.');
            }
            return $this;
        }
        $this->assertRevision($batch->expectedRevision);
        $drafts = [];
        foreach ($batch->propositions as $draft) {
            if (isset($drafts[$draft->key])) {
                throw new FactDbException(Failure::InvalidPlan, 'Duplicate batch proposition key.');
            }
            $drafts[$draft->key] = $draft;
        }
        $state = $this;
        $propositionIds = $claimIds = $claimKeys = $sources = [];
        $lastPosition = -1;
        foreach ($batch->analyses as $index => $analysis) {
            $this->assertCurrent($analysis->source);
            $position = $this->sources[$analysis->source->key()]->position;
            if (isset($sources[$analysis->source->id]) || $position <= $lastPosition) {
                throw new FactDbException(Failure::InvalidPlan, 'Batch sources must be unique and chronologically ordered.');
            }
            $sources[$analysis->source->id] = true;
            $lastPosition = $position;
            $needed = [];
            $claims = [];
            $resolve = static fn (Reference $ref, array $resolved): Reference =>
                $ref->kind === ReferenceKind::Proposed && isset($resolved[$ref->id]) ? Reference::existing($resolved[$ref->id]) : $ref;
            foreach ($analysis->claims as $claim) {
                if (isset($claimKeys[$claim->key])) {
                    throw new FactDbException(Failure::InvalidPlan, 'Batch claim keys must be unique.');
                }
                $claimKeys[$claim->key] = true;
                if ($claim->proposition->kind === ReferenceKind::Proposed && !isset($propositionIds[$claim->proposition->id])) {
                    $draft = $drafts[$claim->proposition->id] ?? null;
                    if ($draft === null) {
                        throw new FactDbException(Failure::InvalidReference, 'Unknown batch proposition.');
                    }
                    $needed[$draft->key] = $draft;
                }
                $links = static fn (array $refs): array => array_map(static fn (Reference $ref): Reference => $resolve($ref, $claimIds), $refs);
                $claims[] = new ClaimDraft($claim->key, $resolve($claim->proposition, $propositionIds), $claim->context,
                    $links($claim->dependsOn), $links($claim->supersedes), $links($claim->contradicts));
            }
            $operation = 'batch-part:' . hash('sha256', $batch->operationId . ':' . $index);
            $state = $state->apply(new ReconciliationPlan($operation, $state->revision, $analysis->source,
                $batch->reviewId, array_values($needed), $claims), $identifiers, $now);
            foreach ($needed as $key => $draft) {
                foreach ($state->propositions as $proposition) {
                    if ($proposition->content !== null && $proposition->identity() === $draft->content->identity()) {
                        $propositionIds[$key] = $proposition->id;
                        break;
                    }
                }
            }
            foreach ($analysis->claims as $offset => $claim) {
                $claimIds[$claim->key] = $state->analyses[$analysis->source->id]->claimIds[$offset];
            }
        }
        foreach ($state->snapshot()->pendingSources as $pending) {
            if (isset($sources[$pending->id])) {
                throw new FactDbException(Failure::InvalidPlan, 'Batch left a selected analysis invalid.');
            }
        }
        if (count($propositionIds) !== count($drafts)) {
            throw new FactDbException(Failure::InvalidPlan, 'Batch contains an unused proposition.');
        }
        return $this->next(propositions: $state->propositions, claims: $state->claims, analyses: $state->analyses,
            operations: array_replace($this->operations, [$batch->operationId => new AppliedOperation(
                $batch->operationId, $batch->fingerprint(), $this->revision + 1)]));
    }

    /** @param list<SourceVersion> $sources @param list<SourceReference> $withdrawals */
    public function synchronize(array $sources, array $withdrawals, int $expectedRevision): self
    {
        $this->assertAvailable();
        $this->assertRevision($expectedRevision);
        Guard::objects($sources, SourceVersion::class, $this->limits->sources);
        Guard::objects($withdrawals, SourceReference::class, $this->limits->sources);
        $state = $this;
        foreach ($sources as $source) {
            $state = $state->register($source, $state->revision);
        }
        foreach ($withdrawals as $source) {
            $state = $state->withdraw($source->id, $source->version, $state->revision);
        }
        return $state === $this ? $this : $this->next(sources: $state->sources, heads: $state->heads);
    }

    public function snapshot(): KnowledgeSnapshot
    {
        $this->assertAvailable();
        $currentSources = [];
        $eligible = [];
        foreach ($this->heads as $id => $version) {
            if ($version === null) {
                continue;
            }
            $reference = new SourceReference((string) $id, $version);
            $currentSources[$id] = $this->sources[$reference->key()];
            $analysis = $this->analyses[$id] ?? null;
            if ($analysis?->source->key() === $reference->key()) {
                foreach ($analysis->claimIds as $claimId) {
                    $claim = $this->claims[$claimId];
                    if ($this->propositions[$claim->propositionId]->content !== null
                        && array_filter($claim->context, fn (SourceReference $ref): bool => ($this->heads[$ref->id] ?? null) !== $ref->version) === []) {
                        $eligible[$claimId] = $claim;
                    }
                }
            }
        }
        // Pruning is conservative: dependent analyses become pending, never silently repaired.
        do {
            [$superseded, $disputed] = $this->classify($eligible);
            $remove = [];
            foreach ($eligible as $id => $claim) {
                foreach ($claim->targets() as $target) {
                    if (!isset($eligible[$target])) {
                        $remove[$id] = true;
                    }
                }
                foreach ($claim->dependsOn as $target) {
                    if (isset($superseded[$target]) || isset($disputed[$target])) {
                        $remove[$id] = true;
                    }
                }
            }
            foreach ($remove as $id => $_) {
                unset($eligible[$id]);
            }
        } while ($remove !== []);
        [$superseded, $disputed] = $this->classify($eligible);
        $pending = [];
        foreach ($currentSources as $source) {
            $analysis = $this->analyses[$source->reference->id] ?? null;
            if ($analysis?->source->key() !== $source->reference->key()
                || array_filter($analysis->claimIds, static fn (string $id): bool => !isset($eligible[$id])) !== []) {
                $pending[] = $source->reference;
            }
        }
        usort($pending, fn (SourceReference $a, SourceReference $b): int =>
            $currentSources[$a->id]->position <=> $currentSources[$b->id]->position);
        $byProposition = [];
        foreach ($eligible as $claim) {
            $byProposition[$claim->propositionId][] = $claim;
        }
        $facts = [];
        foreach ($this->propositions as $proposition) {
            $support = $byProposition[$proposition->id] ?? [];
            $current = array_values(array_filter($support, static fn (Claim $claim): bool => !isset($superseded[$claim->id])));
            $status = $support === [] ? PropositionStatus::Unsupported : ($current === [] ? PropositionStatus::Superseded : PropositionStatus::Supported);
            if (array_filter($current, static fn (Claim $claim): bool => isset($disputed[$claim->id])) !== []) {
                $status = PropositionStatus::Disputed;
            }
            $facts[$proposition->id] = new FactView($proposition, $status, $support);
        }
        return new KnowledgeSnapshot($this->scope, $this->revision, $facts, $eligible, $currentSources,
            $pending, array_map('strval', array_keys($superseded)), array_map('strval', array_keys($disputed)));
    }

    public function assertRevision(int $expectedRevision): void
    {
        Guard::integer($expectedRevision);
        if ($this->revision !== $expectedRevision) {
            throw new FactDbException(Failure::StaleRevision, 'Knowledge changed after the snapshot was read.');
        }
    }

    private function assertAvailable(): void
    {
        if ($this->erased) {
            throw new FactDbException(Failure::Erased, 'This knowledge scope has been erased.');
        }
    }

    private function assertCurrent(SourceReference $reference): void
    {
        if (($this->heads[$reference->id] ?? null) !== $reference->version) {
            throw new FactDbException(Failure::InvalidReference, 'Evidence must reference a current source version in this scope.');
        }
    }

    /** @param array<string, Claim> $claims @return array{array<string, true>, array<string, true>} */
    private function classify(array $claims): array
    {
        $superseded = [];
        foreach ($claims as $claim) {
            foreach ($claim->supersedes as $id) {
                if (isset($claims[$id])) {
                    $superseded[$id] = true;
                }
            }
        }
        $disputed = [];
        foreach ($claims as $claim) {
            foreach ($claim->contradicts as $id) {
                if (isset($claims[$id]) && !isset($superseded[$id]) && !isset($superseded[$claim->id])) {
                    $disputed[$id] = $disputed[$claim->id] = true;
                }
            }
        }
        return [$superseded, $disputed];
    }

    private function next(?array $sources = null, ?array $heads = null, ?array $propositions = null,
        ?array $claims = null, ?array $analyses = null, ?array $operations = null): self
    {
        return new self($this->scope, $this->revision + 1, false, $sources ?? $this->sources,
            $heads ?? $this->heads, $propositions ?? $this->propositions, $claims ?? $this->claims,
            $analyses ?? $this->analyses, $operations ?? $this->operations, $this->limits);
    }

    private function validateRecords(): void
    {
        $latest = [];
        $positions = [];
        $originals = [];
        $versions = [];
        foreach ($this->sources as $key => $source) {
            if ((string) $key !== $source->reference->key()) {
                throw new FactDbException(Failure::InvalidInput, 'Source index does not match its identity.');
            }
            $id = $source->reference->id;
            $original = $originals[$id] ?? $source;
            if ($original->position !== $source->position || $original->occurredAt !== $source->occurredAt) {
                throw new FactDbException(Failure::InvalidInput, 'Source revisions disagree about original chronology.');
            }
            $originals[$id] = $original;
            $versions[$id] = ($versions[$id] ?? 0) + 1;
            $previous = $positions[$source->position] ?? null;
            if ($previous !== null && $previous !== $id) {
                throw new FactDbException(Failure::InvalidInput, 'Source positions collide.');
            }
            $positions[$source->position] = $id;
            $latest[$id] = max($latest[$id] ?? 0, $source->reference->version);
        }
        if (count($latest) !== count($this->heads)) {
            throw new FactDbException(Failure::InvalidReference, 'Every source requires a current or withdrawn head.');
        }
        foreach ($this->heads as $id => $version) {
            Guard::id((string) $id);
            if (!isset($latest[$id]) || $versions[$id] !== $latest[$id] || ($version !== null && (!is_int($version) || $version !== $latest[$id]))) {
                throw new FactDbException(Failure::InvalidReference, 'Source head is unknown or not its latest version.');
            }
        }
        $statements = [];
        foreach ($this->propositions as $id => $proposition) {
            if ((string) $id !== $proposition->id || isset($statements[$proposition->identity()])) {
                throw new FactDbException(Failure::InvalidInput, 'Propositions require unique identities and exact meanings.');
            }
            $statements[$proposition->identity()] = true;
        }
        foreach ($this->claims as $id => $claim) {
            if ((string) $id !== $claim->id || !isset($this->propositions[$claim->propositionId]) || !isset($this->sources[$claim->source->key()])) {
                throw new FactDbException(Failure::InvalidReference, 'Claim references missing knowledge.');
            }
            foreach ($claim->context as $reference) {
                if (!isset($this->sources[$reference->key()])) {
                    throw new FactDbException(Failure::InvalidReference, 'Context evidence is missing.');
                }
            }
            foreach ($claim->targets() as $target) {
                if (!isset($this->claims[$target]) || $target === $claim->id) {
                    throw new FactDbException(Failure::InvalidReference, 'Claim relationship is missing or self-referential.');
                }
            }
            foreach ([...$claim->supersedes, ...$claim->contradicts] as $target) {
                if ($claim->propositionId === $this->claims[$target]->propositionId) {
                    throw new FactDbException(Failure::InvalidPlan, 'A proposition cannot contradict or supersede itself.');
                }
            }
            foreach ($claim->supersedes as $target) {
                $from = $this->sources[$claim->source->key()];
                $to = $this->sources[$this->claims[$target]->source->key()];
                if ($from->actor !== $to->actor || $from->position <= $to->position) {
                    throw new FactDbException(Failure::InvalidPlan, 'A correction requires a later source by the same actor.');
                }
            }
        }
        foreach ($this->analyses as $id => $analysis) {
            if ((string) $id !== $analysis->source->id || !isset($this->sources[$analysis->source->key()])) {
                throw new FactDbException(Failure::InvalidReference, 'Analysis source is missing.');
            }
            foreach ($analysis->claimIds as $claimId) {
                $claim = $this->claims[$claimId] ?? null;
                if ($claim === null || $claim->source->key() !== $analysis->source->key()) {
                    throw new FactDbException(Failure::InvalidReference, 'Analysis contains another source\'s claim.');
                }
            }
        }
        foreach ($this->operations as $id => $operation) {
            if ((string) $id !== $operation->id || $operation->revision > $this->revision) {
                throw new FactDbException(Failure::InvalidInput, 'Invalid operation receipt.');
            }
        }
        $done = [];
        foreach ($this->claims as $claim) {
            $stack = [[$claim->id, false]];
            $visiting = [];
            while ($stack !== []) {
                [$id, $exit] = array_pop($stack);
                if ($exit) {
                    unset($visiting[$id]);
                    $done[$id] = true;
                    continue;
                }
                if (isset($done[$id])) {
                    continue;
                }
                if (isset($visiting[$id])) {
                    throw new FactDbException(Failure::InvalidPlan, 'Claim dependencies cannot contain cycles.');
                }
                $visiting[$id] = true;
                $stack[] = [$id, true];
                foreach ($this->claims[$id]->targets() as $target) {
                    $stack[] = [$target, false];
                }
            }
        }
    }
}
