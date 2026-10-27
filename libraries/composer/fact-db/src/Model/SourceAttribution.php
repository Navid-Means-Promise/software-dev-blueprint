<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class SourceAttribution
{
    public string $occurredAt;

    public function __construct(public SourceReference $reference, public string $actor, public int $position, string $occurredAt)
    {
        Guard::id($actor);
        Guard::integer($position);
        $this->occurredAt = Guard::instant($occurredAt);
    }
}
