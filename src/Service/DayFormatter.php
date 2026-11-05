<?php

namespace App\Service;

/**
 * Formatiert Tageswerte für die Anzeige: ganze Tage ohne Nachkommastelle („3“),
 * halbe Tage mit einer Nachkommastelle im Format der Sprache („2,5“ bzw. „2.5“).
 */
final class DayFormatter
{
    public static function format(int|float|null $days, string $locale = 'de'): string
    {
        if ($days === null) {
            return '';
        }

        $days = round((float) $days, 1);
        if (abs($days - round($days)) < 1e-9) {
            return (string) (int) round($days);
        }

        $decimalSeparator = str_starts_with(strtolower($locale), 'en') ? '.' : ',';

        return number_format($days, 1, $decimalSeparator, '');
    }
}
