<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class Claim
{
    public string $createdAt;

    /**
     * @param list<SourceReference> $context
     * @param list<string> $dependsOn
     * @param list<string> $supersedes
     * @param list<string> $contradicts
     */
    public function __construct(
        public string $id,
        public string $propositionId,
        public SourceReference $source,
        public array $context,
        public array $dependsOn,
        public array $supersedes,
        public array $contradicts,
        string $createdAt
    ) {
        Guard::id($id);
        Guard::id($propositionId);
        Guard::objects($context, SourceReference::class, 20);
        Guard::ids($dependsOn, 20);
        Guard::ids($supersedes, 20);
        Guard::ids($contradicts, 20);
        if (count(array_unique([...$dependsOn, ...$supersedes, ...$contradicts])) !== count($dependsOn) + count($supersedes) + count($contradicts)) {
            throw new \FactDb\FactDbException(\FactDb\Failure::InvalidInput, 'A target cannot have incompatible relationship roles.');
        }
        $keys = array_map(static fn (SourceReference $ref): string => $ref->key(), $context);
        if (count($keys) !== count(array_unique($keys)) || in_array($source->key(), $keys, true)) {
            throw new \FactDb\FactDbException(\FactDb\Failure::InvalidInput, 'Evidence references must be distinct.');
        }
        $this->createdAt = Guard::instant($createdAt);
    }

    /** @return list<string> */
    public function targets(): array
    {
        return array_values(array_unique([...$this->dependsOn, ...$this->supersedes, ...$this->contradicts]));
    }
}
