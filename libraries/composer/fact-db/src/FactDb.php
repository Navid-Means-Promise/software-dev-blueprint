<?php

declare(strict_types=1);

namespace FactDb;

use FactDb\Model\KnowledgeSnapshot;
use FactDb\Model\KnowledgeState;
use FactDb\Model\ReconciliationPlan;
use FactDb\Model\ReconciliationBatch;
use FactDb\Model\SourceReference;
use FactDb\Model\Scope;
use FactDb\Model\SourceVersion;
use FactDb\Port\Clock;
use FactDb\Port\Identifiers;
use FactDb\Port\Repository;
use FactDb\Port\SourceAnalyzer;

/** A trusted, scope-bound server service; host authorization precedes construction. */
final class FactDb
{
    public function __construct(
        private Scope $scope,
        private Repository $repository,
        private Clock $clock,
        private Identifiers $identifiers
    ) {}

    public function snapshot(): KnowledgeSnapshot
    {
        return $this->repository->load($this->scope)->snapshot();
    }

    public function register(SourceVersion $source, int $expectedRevision): KnowledgeSnapshot
    {
        $before = $this->repository->load($this->scope);
        return $this->publish($before, $before->register($source, $expectedRevision));
    }

    public function withdraw(string $sourceId, int $version, int $expectedRevision): KnowledgeSnapshot
    {
        $before = $this->repository->load($this->scope);
        return $this->publish($before, $before->withdraw($sourceId, $version, $expectedRevision));
    }

    public function apply(ReconciliationPlan $plan): KnowledgeSnapshot
    {
        $before = $this->repository->load($this->scope);
        $now = $this->clock->now()->format('Y-m-d\TH:i:s.uP');
        return $this->publish($before, $before->apply($plan, $this->identifiers, $now));
    }

    /** Pure preflight against current state; identifiers are provisional and nothing is persisted. */
    public function validateBatch(ReconciliationBatch $batch): void
    {
        $before = $this->repository->load($this->scope);
        $before->applyBatch($batch, $this->identifiers, $this->clock->now()->format('Y-m-d\TH:i:s.uP'));
    }

    public function applyBatch(ReconciliationBatch $batch): KnowledgeSnapshot
    {
        $before = $this->repository->load($this->scope);
        return $this->publish($before, $before->applyBatch($batch, $this->identifiers, $this->clock->now()->format('Y-m-d\TH:i:s.uP')));
    }

    /** @param list<SourceVersion> $sources @param list<SourceReference> $withdrawals */
    public function synchronize(array $sources, array $withdrawals, int $expectedRevision): KnowledgeSnapshot
    {
        $before = $this->repository->load($this->scope);
        return $this->publish($before, $before->synchronize($sources, $withdrawals, $expectedRevision));
    }

    public function reconcile(string $sourceId, SourceAnalyzer $analyzer): KnowledgeSnapshot
    {
        $snapshot = $this->snapshot();
        $source = $snapshot->sources[$sourceId] ?? null;
        if ($source === null) {
            throw new FactDbException(Failure::InvalidReference, 'Source is not current in this scope.');
        }
        $plan = $analyzer->analyze($source, $snapshot);
        if ($plan->source != $source->reference || $plan->expectedRevision !== $snapshot->revision) {
            throw new FactDbException(Failure::InvalidPlan, 'Analyzer returned another source or snapshot.');
        }
        return $this->apply($plan);
    }

    public function erase(int $expectedRevision): void
    {
        $this->repository->erase($this->scope, $expectedRevision);
    }

    private function publish(KnowledgeState $before, KnowledgeState $after): KnowledgeSnapshot
    {
        if ($before !== $after) {
            $this->repository->commit($after, $before->revision);
        }
        return $after->snapshot();
    }
}
