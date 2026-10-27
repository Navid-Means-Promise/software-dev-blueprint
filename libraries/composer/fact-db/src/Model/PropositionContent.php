<?php

declare(strict_types=1);

namespace FactDb\Model;

use FactDb\{FactDbException, Failure};

/** One assertion; qualifiers restrict its meaning without asserting their premises. */
final readonly class PropositionContent
{
    public string $subject;
    public string $predicate;
    public ?string $object;
    /** @var list<Qualifier> */
    public array $qualifiers;

    /** @param list<Qualifier> $qualifiers */
    public function __construct(string $subject, string $predicate, ?string $object = null, array $qualifiers = [])
    {
        $this->subject = Guard::text($subject, 2048);
        $this->predicate = Guard::text($predicate, 2048);
        $this->object = $object === null ? null : Guard::text($object, 4096);
        Guard::objects($qualifiers, Qualifier::class, 12);
        usort($qualifiers, static fn (Qualifier $a, Qualifier $b): int => strcmp($a->type, $b->type) ?: strcmp($a->value, $b->value));
        foreach ($qualifiers as $index => $qualifier) {
            if ($index > 0 && $qualifier == $qualifiers[$index - 1]) {
                throw new FactDbException(Failure::InvalidInput, 'Repeated qualifier.');
            }
        }
        $this->qualifiers = $qualifiers;
        if (strlen($this->identity()) > 8192) {
            throw new FactDbException(Failure::Capacity, 'Proposition content exceeds its byte budget.');
        }
    }

    public function identity(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** A deterministic display projection, never separately maintained model prose. */
    public function text(): string
    {
        $text = implode(' ', array_values(array_filter([$this->subject, $this->object, $this->predicate], static fn (?string $value): bool => $value !== null)));
        return $text . ($this->qualifiers === [] ? '' : ' (' . implode('؛ ', array_map(
            static fn (Qualifier $qualifier): string => $qualifier->type . ': ' . $qualifier->value, $this->qualifiers)) . ')');
    }
}
