<?php

declare(strict_types=1);

namespace FactDb\Port;

interface Identifiers
{
    public function next(): string;
}
