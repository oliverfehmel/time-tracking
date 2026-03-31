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
     * - persönliche Feiertage raus
     * - genehmigte Abwesenheiten raus
     */
    public function sollSecondsForRange(User $user, DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $from = $from->setTime(0, 0, 0);
        $to = $to->setTime(0, 0, 0);

        $dailySollSeconds = $user->getDailyWorkMinutes() * 60;
        if ($dailySollSeconds <= 0) {
            return 0;
        }

        $holidaySet = $this->buildHolidaySet($user, $from, $to);
        $absenceSet = $this->buildAbsenceSet($user, $from, $to);

        $workdays = $this->countWorkdaysExcluding($from, $to, $holidaySet, $absenceSet);

        return $workdays * $dailySollSeconds;
    }

    /**
     * @return array<string,true> keyed by 'Y-m-d'
     */
    private function buildHolidaySet(User $user, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $holidays = $this->holidayRepo->findForUserBetween($user, $from, $to);

        $set = [];
        foreach ($holidays as $h) {
            $set[$h->getDate()->format('Y-m-d')] = true;
        }

        return $set;
    }

    /** @return array<string,true> keyed by 'Y-m-d' */
    private function buildAbsenceSet(User $user, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $approvedAbsences = $this->absenceRepo->findApprovedForUserBetween($user, $from, $to);

        $set = [];
        foreach ($approvedAbsences as $a) {
            $start = $a->getStartDate()->setTime(0, 0, 0);
            $end   = $a->getEndDate()->setTime(0, 0, 0);

            // Clamp auf [from, to)
            if ($end < $from || $start >= $to) {
                continue;
            }
            if ($start < $from) {
                $start = $from;
            }
            if ($end >= $to) {
                $end = $to->modify('-1 day')->setTime(0, 0, 0);
            }

            for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
                $set[$day->format('Y-m-d')] = true;
            }
        }

        return $set;
    }

    /**
     * Zählt Mo–Fr in [from, to), wenn NICHT im holidaySet und NICHT im absenceSet.
     *
     * @param array<string,true> $holidaySet
     * @param array<string,true> $absenceSet
     */
    private function countWorkdaysExcluding(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        array $holidaySet,
        array $absenceSet
    ): int {
        $count = 0;

        for ($day = $from; $day < $to; $day = $day->modify('+1 day')) {
            $dow = (int) $day->format('N'); // 1=Mon .. 7=Sun
            if ($dow > 5) {
                continue;
            }

            $key = $day->format('Y-m-d');
            if (isset($holidaySet[$key])) {
                continue;
            }
            if (isset($absenceSet[$key])) {
                continue;
            }

            $count++;
        }

        return $count;
    }
}
