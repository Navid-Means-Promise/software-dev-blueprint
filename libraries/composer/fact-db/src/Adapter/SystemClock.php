<?php

declare(strict_types=1);

namespace FactDb\Adapter;

final class SystemClock implements \FactDb\Port\Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
