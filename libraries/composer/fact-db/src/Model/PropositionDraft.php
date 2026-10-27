<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class PropositionDraft
{
    public function __construct(public string $key, public PropositionContent $content)
    {
        Guard::id($key);
    }
}
