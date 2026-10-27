<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class SourceAnalysisDraft
{
    /** @param list<ClaimDraft> $claims */
    public function __construct(public SourceReference $source, public array $claims)
    {
        Guard::objects($claims, ClaimDraft::class);
    }
}
