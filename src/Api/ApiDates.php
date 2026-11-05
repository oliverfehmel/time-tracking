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
     * Parses a validated RFC 3339 string and converts it to the server time zone (entities store wall-clock times).
     */
    public static function parseDateTime(string $value): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!' . self::DATE_TIME, $value)
            ?: new DateTimeImmutable($value);

        return $parsed->setTimezone(self::serverTimeZone());
    }

    private static function serverTimeZone(): DateTimeZone
    {
        return new DateTimeZone(date_default_timezone_get());
    }
}
