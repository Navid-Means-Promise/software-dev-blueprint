<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class SourceReference
{
    public function __construct(public string $id, public int $version)
    {
        Guard::id($id);
        Guard::integer($version, 1);
    }

    public function key(): string
    {
        return $this->id . '@' . $this->version;
    }
}
