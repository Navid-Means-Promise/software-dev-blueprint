<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class SourceVersion
{
    public string $text;
    public string $occurredAt;

    public function __construct(
        public SourceReference $reference,
        public int $position,
        public string $actor,
        string $text,
        string $occurredAt
    ) {
        Guard::integer($position);
        Guard::id($actor);
        $this->text = Guard::text($text, 65536, false, true);
        $this->occurredAt = Guard::instant($occurredAt);
    }
}
