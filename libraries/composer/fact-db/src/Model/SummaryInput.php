<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class SummaryInput
{
    /** @param list<FactView> $facts @param list<SourceAttribution> $evidence */
    public function __construct(public Scope $scope, public int $revision, public array $facts, public array $evidence)
    {
        Guard::integer($revision);
        Guard::objects($facts, FactView::class, Guard::MAX_INTEGER);
        Guard::objects($evidence, SourceAttribution::class, Guard::MAX_INTEGER);
    }
}
