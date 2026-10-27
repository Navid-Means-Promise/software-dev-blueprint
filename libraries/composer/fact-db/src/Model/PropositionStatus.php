<?php

declare(strict_types=1);

namespace FactDb\Model;

enum PropositionStatus: string
{
    case Supported = 'supported';
    case Superseded = 'superseded';
    case Disputed = 'disputed';
    case Unsupported = 'unsupported';
}
