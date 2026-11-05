<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\AbsenceRequestRepository;
use App\Repository\HolidayRepository;
use DateTimeImmutable;

final class WorktimeSollCalculator
{
    public function __construct(
        private readonly HolidayRepository $holidayRepo,
        private readonly AbsenceRequestRepository $absenceRepo,
    ) {}

    /**
     * Soll-Sekunden für Zeitraum [from, to) (to exklusiv) berechnen:
     * - nur Mo–Fr
     * - persönliche Feiertage raus (halbe Feiertage zur Hälfte)
     * - genehmigte Abwesenheiten raus (halbe Abwesenheiten zur Hälfte)
     */
    public function sollSecondsForRange(User $user, DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $from = $from->setTime(0, 0, 0);
        $to = $to->setTime(0, 0, 0);

        $dailySollSeconds = $user->getDailyWorkMinutes() * 60;
        if ($dailySollSeconds <= 0) {
            return 0;
        }

        $holidays = DayPortion::holidayMap($this->holidayRepo->findForUserBetween($user, $from, $to));
        $absences = $this->buildAbsenceMap($user, $from, $to);

        $workdays = $this->countWorkdays($from, $to, $holidays, $absences);

        return (int) round($workdays * $dailySollSeconds);
    }

    /** @return array<string,float> keyed by 'Y-m-d' */
    private function buildAbsenceMap(User $user, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $map = [];
        foreach ($this->absenceRepo->findApprovedForUserBetween($user, $from, $to) as $a) {
            DayPortion::addAbsence($map, $a, $from, $to);
        }

        return $map;
    }

    /**
     * Summiert die Arbeitsanteile der Tage Mo–Fr in [from, to).
     *
     * @param array<string,float> $holidays
     * @param array<string,float> $absences
     */
    private function countWorkdays(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        array $holidays,
        array $absences,
    ): float {
        $count = 0.0;

        for ($day = $from; $day < $to; $day = $day->modify('+1 day')) {
            if ((int) $day->format('N') > 5) { // 1=Mon .. 7=Sun
                continue;
            }

            $key    = $day->format('Y-m-d');
            $count += DayPortion::workFraction($holidays[$key] ?? 0.0, $absences[$key] ?? 0.0);
        }

        return $count;
    }
}
