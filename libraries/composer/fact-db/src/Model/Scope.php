<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class Scope
{
    public function __construct(public string $tenantId, public string $scopeId)
    {
        Guard::id($tenantId);
        Guard::id($scopeId);
    }
}
