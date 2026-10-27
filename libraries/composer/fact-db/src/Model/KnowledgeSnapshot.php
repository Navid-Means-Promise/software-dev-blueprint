<?php

declare(strict_types=1);

namespace FactDb\Model;

use FactDb\FactDbException;
use FactDb\Failure;

final readonly class KnowledgeSnapshot implements \JsonSerializable
{
    public bool $complete;

    /**
     * @param array<string, FactView> $facts
     * @param array<string, Claim> $claims
     * @param array<string, SourceVersion> $sources
     * @param list<SourceReference> $pendingSources
     * @param list<string> $supersededClaimIds
     * @param list<string> $disputedClaimIds
     */
    public function __construct(
        public Scope $scope,
        public int $revision,
        public array $facts,
        public array $claims,
        public array $sources,
        public array $pendingSources,
        public array $supersededClaimIds,
        public array $disputedClaimIds
    ) {
        Guard::integer($revision);
        Guard::objects(array_values($facts), FactView::class, Guard::MAX_INTEGER);
        Guard::objects(array_values($claims), Claim::class, Guard::MAX_INTEGER);
        Guard::objects(array_values($sources), SourceVersion::class, Guard::MAX_INTEGER);
        Guard::objects($pendingSources, SourceReference::class, Guard::MAX_INTEGER);
        Guard::ids($supersededClaimIds, Guard::MAX_INTEGER);
        Guard::ids($disputedClaimIds, Guard::MAX_INTEGER);
        $this->complete = $pendingSources === [];
    }

    public function forSummary(): SummaryInput
    {
        if (!$this->complete) {
            throw new FactDbException(Failure::Incomplete, 'Sources or their dependencies require reconciliation.');
        }
        $facts = array_values(array_filter($this->facts, static fn (FactView $fact): bool =>
            in_array($fact->status, [PropositionStatus::Supported, PropositionStatus::Disputed], true)));
        $facts = array_map(fn (FactView $fact): FactView => new FactView($fact->proposition, $fact->status,
            array_values(array_filter($fact->claims, fn (Claim $claim): bool => !in_array($claim->id, $this->supersededClaimIds, true)))), $facts);
        $evidence = [];
        foreach ($facts as $fact) {
            foreach ($fact->claims as $claim) {
                foreach ([$claim->source, ...$claim->context] as $reference) {
                    $source = $this->sources[$reference->id];
                    $evidence[$reference->key()] = new SourceAttribution($reference, $source->actor, $source->position, $source->occurredAt);
                }
            }
        }
        return new SummaryInput($this->scope, $this->revision, $facts, array_values($evidence));
    }

    /** @return array<string, Scope|int|bool|object|array> */
    public function jsonSerialize(): array
    {
        return [
            'scope' => $this->scope,
            'revision' => $this->revision,
            'facts' => (object) $this->facts,
            'claims' => (object) $this->claims,
            'sources' => (object) $this->sources,
            'pendingSources' => $this->pendingSources,
            'supersededClaimIds' => $this->supersededClaimIds,
            'disputedClaimIds' => $this->disputedClaimIds,
            'complete' => $this->complete,
        ];
    }
}
