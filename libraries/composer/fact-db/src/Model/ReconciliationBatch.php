<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class ReconciliationBatch
{
    /** @param list<PropositionDraft> $propositions @param list<SourceAnalysisDraft> $analyses */
    public function __construct(
        public string $operationId,
        public int $expectedRevision,
        public string $reviewId,
        public array $propositions,
        public array $analyses
    ) {
        Guard::id($operationId);
        Guard::integer($expectedRevision);
        Guard::id($reviewId);
        Guard::objects($propositions, PropositionDraft::class);
        Guard::objects($analyses, SourceAnalysisDraft::class);
        if ($analyses === []) {
            throw new \FactDb\FactDbException(\FactDb\Failure::InvalidPlan, 'A batch requires source analyses.');
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
