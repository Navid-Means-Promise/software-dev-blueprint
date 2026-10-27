<?php

declare(strict_types=1);

namespace FactDb\Model;

use DateTimeImmutable;
use DateTimeZone;
use FactDb\FactDbException;
use FactDb\Failure;

final class Guard
{
    public const MAX_INTEGER = 9007199254740991;

    public static function id(string $value): void
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/', $value) !== 1) {
            throw new FactDbException(Failure::InvalidInput, 'Invalid identifier.');
        }
    }

    public static function integer(int $value, int $minimum = 0): void
    {
        if ($value < $minimum || $value > self::MAX_INTEGER) {
            throw new FactDbException(Failure::InvalidInput, 'Integer is outside the contract range.');
        }
    }

    public static function text(string $value, int $maximum, bool $trim = true, bool $allowEmpty = false): string
    {
        if ((!$allowEmpty && trim($value) === '') || strlen($value) > $maximum || preg_match('//u', $value) !== 1
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
            throw new FactDbException(Failure::InvalidInput, 'Text is empty, invalid, or exceeds its byte limit.');
        }
        return $trim ? trim($value) : $value;
    }

    public static function instant(string $value): string
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/', $value) !== 1) {
            throw new FactDbException(Failure::InvalidInput, 'Timestamp must be an RFC 3339 instant.');
        }
        try {
            $date = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            if (is_array($errors) && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0)) {
                throw new FactDbException(Failure::InvalidInput, 'Timestamp contains an invalid date.');
            }
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        } catch (\Exception $error) {
            throw new FactDbException(Failure::InvalidInput, 'Invalid timestamp.', $error);
        }
    }

    /** @param list<mixed> $values */
    public static function objects(array $values, string $class, int $maximum = 100): void
    {
        if (!array_is_list($values) || count($values) > $maximum) {
            throw new FactDbException(Failure::Capacity, 'Collection is not a bounded list.');
        }
        foreach ($values as $value) {
            if (!$value instanceof $class) {
                throw new FactDbException(Failure::InvalidInput, 'Collection contains an invalid value.');
            }
        }
    }

    /** @param list<string> $values */
    public static function ids(array $values, int $maximum = 100): void
    {
        if (!array_is_list($values) || count($values) > $maximum) {
            throw new FactDbException(Failure::Capacity, 'Identifier collection exceeds its limit.');
        }
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new FactDbException(Failure::InvalidInput, 'Identifier is not text.');
            }
            self::id($value);
        }
        if (count(array_unique($values)) !== count($values)) {
            throw new FactDbException(Failure::InvalidInput, 'Duplicate identifiers are not permitted.');
        }
    }
}
