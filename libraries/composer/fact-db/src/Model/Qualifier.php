<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class Qualifier
{
    public string $type;
    public string $value;

    public function __construct(string $type, string $value)
    {
        $this->type = Guard::text($type, 128);
        $this->value = Guard::text($value, 2048);
    }
}
