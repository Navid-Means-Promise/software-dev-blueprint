<?php

declare(strict_types=1);

namespace FactDb\Analysis;

use FactDb\Model\{KnowledgeSnapshot, SourceReference, SourceVersion};
use LengthException;

final class AnalysisPrompt
{
    public const MAX_SOURCES = 8;

    /** Host context is serialized data, not interpolated instructions. */
    public function build(KnowledgeSnapshot $snapshot, string $contextJson, int $maximumBytes): AnalysisRequest
    {
        $selected = [];
        $request = null;
        foreach (array_slice($snapshot->pendingSources, 0, self::MAX_SOURCES) as $reference) {
            $candidate = $this->request($snapshot, [...$selected, $reference], $contextJson);
            if (strlen(json_encode($candidate->messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > $maximumBytes) {
                break;
            }
            $selected[] = $reference;
            $request = $candidate;
        }
        if ($request === null) {
            throw new LengthException('Required analysis context exceeds the complete-request budget.');
        }
        return $request;
    }

    /** @param list<SourceReference> $selected */
    private function request(KnowledgeSnapshot $snapshot, array $selected, string $contextJson): AnalysisRequest
    {
        $ids = array_map(static fn (SourceReference $ref): string => $ref->id, $selected);
        $references = new AnalysisReferences($snapshot);
        $sources = $snapshot->sources;
        uasort($sources, static fn (SourceVersion $a, SourceVersion $b): int => $a->position <=> $b->position);
        $ordered = array_values($sources);
        $context = [];
        foreach ($ordered as $index => $source) {
            if (in_array($source->reference->id, $ids, true)) {
                foreach (array_slice($ordered, max(0, $index - 4), min(4, $index) + 1) as $nearby) {
                    $context[$nearby->reference->id] = $nearby;
                }
            }
        }
        $claims = [];
        foreach ($snapshot->claims as $claim) {
            if (!in_array($claim->source->id, $ids, true)) {
                $source = $snapshot->sources[$claim->source->id];
                $record = ['id' => $references->claims[$claim->id], 'propositionId' => $references->propositions[$claim->propositionId],
                    'sourceId' => $claim->source->id, 'actor' => $source->actor,
                    'position' => $source->position, 'occurredAt' => $source->occurredAt];
                foreach (['context', 'dependsOn', 'supersedes', 'contradicts'] as $field) {
                    if ($claim->$field !== []) {
                        $record[$field] = $field === 'context'
                            ? array_map(static fn (SourceReference $ref): string => $ref->id, $claim->context)
                            : array_map(static fn (string $id): string => $references->claims[$id], $claim->$field);
                    }
                }
                $claims[] = $record;
                foreach ($claim->context as $reference) {
                    $context[$reference->id] = $snapshot->sources[$reference->id];
                }
            }
        }
        $facts = array_map(static fn ($fact): array => ['id' => $references->propositions[$fact->proposition->id], ...get_object_vars($fact->proposition->content),
            'status' => $fact->status->value], array_values(array_filter($snapshot->facts, static fn ($fact): bool => $fact->proposition->content !== null)));
        $input = ['hostContext' => json_decode($contextJson, false, 32, JSON_THROW_ON_ERROR), 'requestedSources' => $ids,
            'sources' => array_map(static fn (SourceReference $ref): SourceVersion => $snapshot->sources[$ref->id], $selected),
            'contextSources' => array_values(array_diff_key($context, array_fill_keys($ids, true))),
            'propositions' => $facts, 'claims' => $claims];
        return new AnalysisRequest($snapshot, $selected, [
            ['role' => 'system', 'content' => AnalysisRequest::SYSTEM],
            ['role' => 'user', 'content' => json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ], array_map('strval', array_keys($context)), $references);
    }
}
