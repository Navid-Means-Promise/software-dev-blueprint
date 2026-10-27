<?php

declare(strict_types=1);

namespace FactDb\Analysis;

use FactDb\Model\KnowledgeSnapshot;

/** Request-local aliases keep persistent identifier length out of the model's context budget. */
final readonly class AnalysisReferences
{
    /** @var array<string, string> Persistent proposition ID => supplied alias. */
    public array $propositions;
    /** @var array<string, string> Persistent claim ID => supplied alias. */
    public array $claims;

    public function __construct(KnowledgeSnapshot $snapshot)
    {
        $reserved = array_fill_keys([...array_keys($snapshot->facts), ...array_keys($snapshot->claims), ...array_keys($snapshot->sources)], true);
        $aliases = static function (array $ids, string $prefix) use (&$reserved): array {
            $map = [];
            $index = 0;
            foreach ($ids as $id) {
                do {
                    $alias = $prefix . ++$index;
                } while (isset($reserved[$alias]));
                $reserved[$alias] = true;
                $map[$id] = $alias;
            }
            return $map;
        };
        $this->propositions = $aliases(array_keys($snapshot->facts), 'fact');
        $this->claims = $aliases(array_keys($snapshot->claims), 'claim');
    }
}
