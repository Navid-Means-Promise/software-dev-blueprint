<?php

declare(strict_types=1);

namespace FactDb\Model;

final readonly class ClaimDraft
{
    /**
     * @param list<SourceReference> $context
     * @param list<Reference> $dependsOn
     * @param list<Reference> $supersedes
     * @param list<Reference> $contradicts
     */
    public function __construct(
        public string $key,
        public Reference $proposition,
        public array $context = [],
        public array $dependsOn = [],
        public array $supersedes = [],
        public array $contradicts = []
    ) {
        Guard::id($key);
        Guard::objects($context, SourceReference::class, 20);
        foreach ([$dependsOn, $supersedes, $contradicts] as $references) {
            Guard::objects($references, Reference::class, 20);
            $keys = array_map(static fn (Reference $ref): string => $ref->kind->value . ':' . $ref->id, $references);
            if (count(array_unique($keys)) !== count($keys)) {
                throw new \FactDb\FactDbException(\FactDb\Failure::InvalidInput, 'Duplicate draft references.');
            }
        }
    }
}
