<?php

declare(strict_types=1);

namespace FactDb;

use RuntimeException;
use Throwable;

final class FactDbException extends RuntimeException
{
    public function __construct(public readonly Failure $reason, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
