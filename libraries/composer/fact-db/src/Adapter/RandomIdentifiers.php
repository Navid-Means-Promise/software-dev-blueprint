<?php

declare(strict_types=1);

namespace FactDb\Adapter;

final class RandomIdentifiers implements \FactDb\Port\Identifiers
{
    public function next(): string
    {
        return bin2hex(random_bytes(16));
    }
}
