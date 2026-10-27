<?php

declare(strict_types=1);

namespace FactDb\Persistence;

use FactDb\FactDbException;
use FactDb\Failure;
use FactDb\Model\{AppliedOperation, Claim, Proposition, SourceAnalysis, SourceReference, SourceVersion};

final class RecordCodec
{
    public static function encode(SourceVersion|Proposition|Claim|SourceAnalysis|AppliedOperation $record): string
    {
        return json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function decode(string $kind, string $json): SourceVersion|Proposition|Claim|SourceAnalysis|AppliedOperation
    {
        try {
            $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($value)) {
                throw new FactDbException(Failure::Schema, 'Stored FactDB record is not an object.');
            }
            return match ($kind) {
                'sources' => self::source($value),
                'propositions' => self::proposition($value),
                'claims' => self::claim($value),
                'analyses' => self::analysis($value),
                'operations' => self::operation($value),
                default => throw new FactDbException(Failure::Schema, 'Unknown record codec.'),
            };
        } catch (\JsonException|\TypeError|\ValueError $error) {
            throw new FactDbException(Failure::Schema, 'Stored FactDB record is malformed.', $error);
        }
    }

    /** @param array<string, mixed> $value */
    private static function source(array $value): SourceVersion
    {
        self::keys($value, ['reference', 'position', 'actor', 'text', 'occurredAt']);
        return new SourceVersion(self::reference($value['reference']), self::integer($value['position']),
            self::string($value['actor']), self::string($value['text']), self::string($value['occurredAt']));
    }

    private static function proposition(array $value): Proposition
    {
        if (array_key_exists('statement', $value)) {
            self::keys($value, ['id', 'statement', 'createdAt']);
            return new Proposition(self::string($value['id']), self::string($value['statement']), self::string($value['createdAt']));
        }
        self::keys($value, ['id', 'subject', 'predicate', 'object', 'qualifiers', 'createdAt']);
        $content = $value;
        unset($content['id'], $content['createdAt']);
        try {
            return new Proposition(self::string($value['id']), \FactDb\Analysis\ContentDecoder::decode($content), self::string($value['createdAt']));
        } catch (FactDbException $error) {
            throw new FactDbException(Failure::Schema, 'Invalid stored proposition.', $error);
        }
    }

    private static function claim(array $value): Claim
    {
        self::keys($value, ['id', 'propositionId', 'source', 'context', 'dependsOn', 'supersedes', 'contradicts', 'createdAt']);
        return new Claim(self::string($value['id']), self::string($value['propositionId']), self::reference($value['source']),
            array_map(self::reference(...), self::items($value['context'])), self::strings($value['dependsOn']),
            self::strings($value['supersedes']), self::strings($value['contradicts']), self::string($value['createdAt']));
    }

    private static function analysis(array $value): SourceAnalysis
    {
        self::keys($value, ['source', 'claimIds', 'reviewId']);
        return new SourceAnalysis(self::reference($value['source']), self::strings($value['claimIds']), self::string($value['reviewId']));
    }

    private static function operation(array $value): AppliedOperation
    {
        self::keys($value, ['id', 'fingerprint', 'revision']);
        return new AppliedOperation(self::string($value['id']), self::string($value['fingerprint']), self::integer($value['revision']));
    }

    private static function reference(mixed $value): SourceReference
    {
        if (!is_array($value)) {
            throw new FactDbException(Failure::Schema, 'Stored source reference is not an object.');
        }
        self::keys($value, ['id', 'version']);
        return new SourceReference(self::string($value['id']), self::integer($value['version']));
    }

    private static function keys(array $value, array $expected): void
    {
        if (count($value) !== count($expected) || array_diff($expected, array_keys($value)) !== []) {
            throw new FactDbException(Failure::Schema, 'Stored FactDB record has an unexpected shape.');
        }
    }

    private static function string(mixed $value): string
    {
        if (!is_string($value)) {
            throw new FactDbException(Failure::Schema, 'Stored text has an invalid type.');
        }
        return $value;
    }

    private static function integer(mixed $value): int
    {
        if (!is_int($value)) {
            throw new FactDbException(Failure::Schema, 'Stored counter has an invalid type.');
        }
        return $value;
    }

    /** @return list<mixed> */
    private static function items(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > 100) {
            throw new FactDbException(Failure::Schema, 'Stored list is malformed or oversized.');
        }
        return $value;
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        return array_map(self::string(...), self::items($value));
    }
}
