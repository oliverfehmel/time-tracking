<?php

namespace App\Api;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Date formats of the API: dates as `YYYY-MM-DD`, points in time as RFC 3339 with offset (server time zone on output,
 * any offset on input).
 */
final class ApiDates
{
    public const string DATE      = 'Y-m-d';
    public const string DATE_TIME = DateTimeInterface::RFC3339;

    public static function date(?DateTimeInterface $date): ?string
    {
        return $date?->format(self::DATE);
    }

    public static function dateTime(?DateTimeInterface $dateTime): ?string
    {
        return $dateTime === null
            ? null
            : DateTimeImmutable::createFromInterface($dateTime)->setTimezone(self::serverTimeZone())->format(self::DATE_TIME);
    }

    /**
     * Parses a validated `YYYY-MM-DD` string to midnight in the server time zone.
     */
    public static function parseDate(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' 00:00:00', self::serverTimeZone());
    }

    /**
     * Parses an RFC 3339 point in time with offset (`Z` or `±hh:mm`, optional fractional seconds, e.g. the output of
     * JavaScript's toISOString()), converted to the server time zone and truncated to whole seconds (entities store
     * wall-clock times without fractions). Returns null for anything else.
     */
    public static function tryParseDateTime(string $value): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/i', $value) !== 1) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s' . (str_contains($value, '.') ? '.u' : '') . 'P', strtoupper($value));
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        $parsed = $parsed->setTimezone(self::serverTimeZone());

        return $parsed->setTime((int) $parsed->format('H'), (int) $parsed->format('i'), (int) $parsed->format('s'));
    }

    /**
     * Like {@see self::tryParseDateTime()} for values that were already validated.
     */
    public static function parseDateTime(string $value): DateTimeImmutable
    {
        return self::tryParseDateTime($value) ?? throw new \InvalidArgumentException(sprintf('"%s" is not an RFC 3339 point in time.', $value));
    }

    private static function serverTimeZone(): DateTimeZone
    {
        return new DateTimeZone(date_default_timezone_get());
    }
}
