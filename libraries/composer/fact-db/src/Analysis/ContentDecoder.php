<?php

declare(strict_types=1);

namespace FactDb\Analysis;

use FactDb\{FactDbException, Failure};
use FactDb\Model\{PropositionContent, Qualifier};
use stdClass;

/** Shared structured-content boundary for model output and durable records. */
final class ContentDecoder
{
    public static function decode(mixed $value): PropositionContent
    {
        $fields = self::fields($value, ['subject', 'predicate', 'object', 'qualifiers']);
        if (!is_string($fields['subject']) || !is_string($fields['predicate'])
            || ($fields['object'] !== null && !is_string($fields['object']))
            || !is_array($fields['qualifiers']) || !array_is_list($fields['qualifiers']) || count($fields['qualifiers']) > 12) {
            throw new FactDbException(Failure::InvalidInput, 'Invalid proposition content.');
        }
        $qualifiers = [];
        foreach ($fields['qualifiers'] as $qualifier) {
            $item = self::fields($qualifier, ['type', 'value']);
            if (!is_string($item['type']) || !is_string($item['value'])) {
                throw new FactDbException(Failure::InvalidInput, 'Qualifiers require plain text.');
            }
            $qualifiers[] = new Qualifier($item['type'], $item['value']);
        }
        return new PropositionContent($fields['subject'], $fields['predicate'], $fields['object'], $qualifiers);
    }

    /** @param list<string> $keys @return array<string, mixed> */
    private static function fields(mixed $value, array $keys): array
    {
        $fields = $value instanceof stdClass ? get_object_vars($value) : $value;
        if (!is_array($fields) || count($fields) !== count($keys) || array_diff($keys, array_keys($fields)) !== []) {
            throw new FactDbException(Failure::InvalidInput, 'Unexpected content fields.');
        }
        return $fields;
    }
}
