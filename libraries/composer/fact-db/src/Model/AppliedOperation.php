<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class AppliedOperation
{
    public function __construct(public string $id, public string $fingerprint, public int $revision)
    {
        Guard::id($id);
        Guard::integer($revision, 1);
        if (preg_match('/\A[0-9a-f]{64}\z/', $fingerprint) !== 1) {
            throw new \FactDb\FactDbException(\FactDb\Failure::InvalidInput, 'Invalid operation fingerprint.');
        }
    }
}
