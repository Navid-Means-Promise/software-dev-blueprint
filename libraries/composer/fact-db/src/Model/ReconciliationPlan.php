<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class ReconciliationPlan
{
    /** @param list<PropositionDraft> $propositions @param list<ClaimDraft> $claims */
    public function __construct(
        public string $operationId,
        public int $expectedRevision,
        public SourceReference $source,
        public string $reviewId,
        public array $propositions,
        public array $claims
    ) {
        Guard::id($operationId);
        Guard::integer($expectedRevision);
        Guard::id($reviewId);
        Guard::objects($propositions, PropositionDraft::class);
        Guard::objects($claims, ClaimDraft::class);
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
