<?php

namespace App\Service;

use App\Entity\AbsenceRequest;
use App\Entity\Holiday;
use DateTimeImmutable;

/**
 * Hilfsfunktionen für Tagesanteile (ganzer Tag = 1.0, halber Tag = 0.5).
 *
 * Ein Werktag hat den Wert 1. Feiertage und Abwesenheiten belegen davon jeweils einen Anteil:
 * - Soll-Anteil des Tages       = max(0, 1 - Feiertag - Abwesenheit)
 * - angerechnete Abwesenheit     = min(Abwesenheit, 1 - Feiertag)
 */
final class DayPortion
{
    public const float FULL = 1.0;
    public const float HALF = 0.5;

    /**
     * Baut eine Map Datum → Feiertagsanteil. Mehrere Feiertage am selben Tag ergeben das Maximum.
     *
     * @param iterable<Holiday> $holidays
     * @return array<string,float> keyed by 'Y-m-d'
     */
    public static function holidayMap(iterable $holidays): array
    {
        $map = [];
        foreach ($holidays as $h) {
            $key       = $h->getDate()->format('Y-m-d');
            $map[$key] = max($map[$key] ?? 0.0, $h->getDayFraction());
        }

        return $map;
    }

    /**
     * Trägt den Anteil einer Abwesenheit für alle Tage in [from, toExclusive) in die Map ein.
     * Mehrere Abwesenheiten am selben Tag werden addiert und auf einen ganzen Tag begrenzt.
     *
     * @param array<string,float> $map keyed by 'Y-m-d'
     */
    public static function addAbsence(
        array &$map,
        AbsenceRequest $absence,
        DateTimeImmutable $from,
        DateTimeImmutable $toExclusive,
    ): void {
        $start = max($absence->getStartDate()->setTime(0, 0), $from->setTime(0, 0));
        $end   = min($absence->getEndDate()->setTime(0, 0)->modify('+1 day'), $toExclusive->setTime(0, 0));

        for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
            $key       = $day->format('Y-m-d');
            $map[$key] = min(self::FULL, ($map[$key] ?? 0.0) + $absence->getDayFraction());
        }
    }

    /**
     * Verbleibender Arbeitsanteil eines Werktags nach Abzug von Feiertag und Abwesenheit.
     */
    public static function workFraction(float $holiday, float $absence): float
    {
        return max(0.0, self::FULL - $holiday - $absence);
    }

    /**
     * Anteil einer Abwesenheit, der tatsächlich als Abwesenheitstag angerechnet wird
     * (ein Feiertag reduziert die anrechenbare Zeit).
     */
    public static function chargeableFraction(float $holiday, float $absence): float
    {
        return min($absence, max(0.0, self::FULL - $holiday));
    }

    /**
     * Prüft, ob ein neuer Antrag neben bereits bestehenden (überlappenden) Anträgen Platz hat:
     * Die Summe aller Anteile darf einen ganzen Tag nicht überschreiten. Zwei halbe Tage am selben Tag
     * sind also erlaubt, jede andere Überlappung nicht.
     *
     * @param AbsenceRequest[] $overlapping bereits offene/genehmigte Anträge, die den Zeitraum überlappen
     */
    public static function fitsAlongside(float $newFraction, array $overlapping): bool
    {
        if ($overlapping === []) {
            return true;
        }

        $sum = $newFraction;
        foreach ($overlapping as $existing) {
            if (!$existing->isHalfDay()) {
                return false;
            }
            $sum += $existing->getDayFraction();
        }

        return $sum <= self::FULL;
    }
}
