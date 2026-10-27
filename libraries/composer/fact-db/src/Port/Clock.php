<?php

declare(strict_types=1);

namespace FactDb\Port;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
