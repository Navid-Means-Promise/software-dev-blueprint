<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class Proposition implements \JsonSerializable
{
    public string $statement;
    public string $createdAt;
    public ?PropositionContent $content;

    /** Strings are retained only for decoding immutable v1 provenance, never new drafts. */
    public function __construct(public string $id, PropositionContent|string $content, string $createdAt)
    {
        Guard::id($id);
        $this->content = $content instanceof PropositionContent ? $content : null;
        $this->statement = $content instanceof PropositionContent ? $content->text() : Guard::text($content, 8192);
        $this->createdAt = Guard::instant($createdAt);
    }

    public function identity(): string
    {
        return $this->content?->identity() ?? $this->statement;
    }

    public function jsonSerialize(): array
    {
        return $this->content === null ? ['id' => $this->id, 'statement' => $this->statement, 'createdAt' => $this->createdAt]
            : ['id' => $this->id, ...get_object_vars($this->content), 'createdAt' => $this->createdAt];
    }
}
