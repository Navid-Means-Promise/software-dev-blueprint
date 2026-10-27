<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class Reference
{
    public function __construct(public ReferenceKind $kind, public string $id)
    {
        Guard::id($id);
    }

    public static function existing(string $id): self
    {
        return new self(ReferenceKind::Existing, $id);
    }

    public static function proposed(string $key): self
    {
        return new self(ReferenceKind::Proposed, $key);
    }
}
