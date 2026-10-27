<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class FactView
{
    /** @param list<Claim> $claims */
    public function __construct(public Proposition $proposition, public PropositionStatus $status, public array $claims)
    {
        Guard::objects($claims, Claim::class, Guard::MAX_INTEGER);
        foreach ($claims as $claim) {
            if ($claim->propositionId !== $proposition->id) {
                throw new \FactDb\FactDbException(\FactDb\Failure::InvalidReference, 'Fact view contains another proposition\'s claim.');
            }
        }
    }
}
