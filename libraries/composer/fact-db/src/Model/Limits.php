<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class Limits
{
    public function __construct(
        public int $sources = 1000,
        public int $sourceVersions = 5000,
        public int $propositions = 2000,
        public int $claims = 5000,
        public int $operations = 5000,
        public int $sourceBytes = 4194304
    ) {
        foreach (get_object_vars($this) as $value) {
            Guard::integer($value, 1);
        }
    }
}
