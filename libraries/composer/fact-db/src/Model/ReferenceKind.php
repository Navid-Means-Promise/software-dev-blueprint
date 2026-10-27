<?php

declare(strict_types=1);

namespace FactDb\Model;

enum ReferenceKind: string
{
    case Existing = 'existing';
    case Proposed = 'proposed';
}
