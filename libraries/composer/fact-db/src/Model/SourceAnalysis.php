<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class SourceAnalysis
{
    /** @param list<string> $claimIds */
    public function __construct(public SourceReference $source, public array $claimIds, public string $reviewId)
    {
        Guard::ids($claimIds);
        Guard::id($reviewId);
    }
}
