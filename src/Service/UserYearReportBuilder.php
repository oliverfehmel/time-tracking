<?php

namespace App\Service;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\AbsenceRequestRepository;
use App\Repository\HolidayRepository;
use App\Repository\TimeEntryRepository;
use App\Entity\WorkLocationType;
use App\Repository\WorkLocationRepository;
use App\Repository\WorkLocationTypeRepository;
use DateTimeImmutable;

final class UserYearReportBuilder
{
    public function __construct(
        private readonly TimeEntryRepository $timeEntryRepo,
        private readonly HolidayRepository $holidayRepo,
        private readonly AbsenceRequestRepository $absenceRepo,
        private readonly WorkLocationRepository $workLocationRepo,
        private readonly WorkLocationTypeRepository $workLocationTypeRepo,
        private readonly TimeTrackingCalculator $calc,
        private readonly WorktimeSollCalculator $sollCalc,
    ) {}

    public function build(User $user, int $year, Settings $settings): array
    {
        $now       = new DateTimeImmutable('now');
        $yearStart = new DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year));
        $yearEnd   = $yearStart->modify('+1 year');

        $entries            = $this->timeEntryRepo->findForUserBetween($user, $yearStart, $yearEnd);
        $holidaySet         = $this->buildHolidaySet($user, $yearStart, $yearEnd);
        $absenceSets        = $this->buildAbsenceSets($user, $yearStart, $yearEnd);
        $workLocationMap    = $this->workLocationRepo->buildTypeMapForUser($user, $yearStart, $yearEnd);
        $defaultLocationType = $this->workLocationTypeRepo->findDefault();
        $byMonthDay         = $this->groupEntriesByMonthDay($entries);

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[] = $this->buildMonth($user, $year, $m, $entries, $byMonthDay, $holidaySet, $absenceSets, $workLocationMap, $defaultLocationType, $settings, $now);
        }

        return $months;
    }

    // --- Lookup-Set-Builder ---

    private function buildHolidaySet(User $user, DateTimeImmutable $yearStart, DateTimeImmutable $yearEnd): array
    {
        $set = [];
        foreach ($this->holidayRepo->findForUserBetween($user, $yearStart, $yearEnd) as $h) {
            $set[$h->getDate()->format('Y-m-d')] = true;
        }
        return $set;
    }

    private function buildAbsenceSets(User $user, DateTimeImmutable $yearStart, DateTimeImmutable $yearEnd): array
    {
        $absenceSet     = [];
        $absenceInfoSet = [];

        foreach ($this->absenceRepo->findApprovedForUserBetween($user, $yearStart, $yearEnd) as $a) {
            $start = $a->getStartDate()->setTime(0, 0);
            $end   = $a->getEndDate()->setTime(0, 0);

            // Clamp to year range
            if ($end < $yearStart || $start >= $yearEnd) {
                continue;
            }
            $start = max($start, $yearStart);
            $end   = min($end, $yearEnd->modify('-1 day')->setTime(0, 0));

            for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
                $key = $day->format('Y-m-d');
                $absenceSet[$key]     = true;
                $absenceInfoSet[$key] = [
                    'typeName' => $a->getType()->getName(),
                    'typeKey'  => $a->getType()->getKeyName(),
                ];
            }
        }

        return compact('absenceSet', 'absenceInfoSet');
    }

    private function groupEntriesByMonthDay(array $entries): array
    {
        $map = [];
        foreach ($entries as $e) {
            $map[$e->getStartedAt()->format('Y-m')][$e->getStartedAt()->format('Y-m-d')][] = $e;
        }
        return $map;
    }

    private function buildMonth(
        User $user,
        int $year,
        int $m,
        array $allEntries,
        array $byMonthDay,
        array $holidaySet,
        array $absenceSets,
        array $workLocationMap,
        ?WorkLocationType $defaultLocationType,
        Settings $settings,
        DateTimeImmutable $now,
    ): array {
        $monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $m));
        $monthEnd   = $monthStart->modify('+1 month');
        $monthKey   = $monthStart->format('Y-m');

        $daysWithEntries  = $byMonthDay[$monthKey] ?? [];
        ksort($daysWithEntries);

        $tomorrow         = $now->setTime(0, 0)->modify('+1 day');
        $sollEnd          = $monthEnd <= $tomorrow ? $monthEnd : $tomorrow;
        $monthSollSeconds = $monthStart < $tomorrow
            ? $this->sollCalc->sollSecondsForRange($user, $monthStart, $sollEnd)
            : 0;
        $monthIstSeconds  = 0;
        $dayRows          = [];

        for ($day = $monthStart; $day < $monthEnd; $day = $day->modify('+1 day')) {
            $row              = $this->buildDayRow($user, $day, $allEntries, $daysWithEntries, $holidaySet, $absenceSets, $workLocationMap, $defaultLocationType, $settings, $now);
            $monthIstSeconds += $row['netSeconds'];
            $dayRows[]        = $row;
        }

        $monthDelta = $monthIstSeconds - $monthSollSeconds;

        return [
            'key'          => $monthKey,
            'label'        => $monthStart->format('F Y'),
            'start'        => $monthStart,
            'ist'          => $this->calc->formatSeconds($monthIstSeconds),
            'soll'         => $this->calc->formatSeconds($monthSollSeconds),
            'delta'        => $this->calc->formatSignedSeconds($monthDelta),
            'deltaSeconds' => $monthDelta,
            'days'         => $dayRows,
            'hasWork'      => $monthIstSeconds > 0,
            'editable'     => $this->calc->isEditableMonth($monthStart, $now),
        ];
    }

    private function buildDayRow(
        User $user,
        DateTimeImmutable $day,
        array $allEntries,
        array $daysWithEntries,
        array $holidaySet,
        array $absenceSets,
        array $workLocationMap,
        ?WorkLocationType $defaultLocationType,
        Settings $settings,
        DateTimeImmutable $now,
    ): array {
        $dayStart  = $day->setTime(0, 0);
        $dayEnd    = $dayStart->modify('+1 day');
        $dayKey    = $dayStart->format('Y-m-d');
        $dow       = (int) $dayStart->format('N');

        $isWorkday = $dow <= 5;
        $isHoliday = isset($holidaySet[$dayKey]);
        $isAbsence = isset($absenceSets['absenceSet'][$dayKey]);

        $isFuture       = $dayStart > $now->setTime(0, 0);
        $daySollSeconds = (!$isFuture && $isWorkday && !$isHoliday && !$isAbsence)
            ? ($user->getDailyWorkMinutes() * 60)
            : 0;

        $raw = $this->calc->workedSecondsInRange($allEntries, $dayStart, $dayEnd, $now);
        $net = $this->calc->applyDailyBreakRuleSeconds($raw, $settings);

        [$firstStart, $lastEnd] = $this->extractDayStartEnd($daysWithEntries[$dayKey] ?? [], $dayStart, $dayEnd, $now);

        $absenceInfo  = $absenceSets['absenceInfoSet'][$dayKey] ?? null;
        $dayDelta     = $net - $daySollSeconds;
        $isRealWorkday = $isWorkday && !$isHoliday && !$isAbsence;
        $hasEntries    = !empty($daysWithEntries[$dayKey] ?? []);
        $workLocation  = $workLocationMap[$dayKey] ?? ($isRealWorkday && $hasEntries ? $defaultLocationType : null);

        return [
            'date'              => $dayStart,
            'start'             => $firstStart,
            'end'               => $lastEnd,
            'ist'               => $this->calc->formatSeconds($net),
            'soll'              => $this->calc->formatSeconds($daySollSeconds),
            'delta'             => $this->calc->formatSignedSeconds($dayDelta),
            'deltaSeconds'      => $dayDelta,
            'netSeconds'        => $net,
            'rowClass'          => $this->buildRowClass($isWorkday, $isHoliday, $isAbsence, empty($daysWithEntries[$dayKey] ?? [])),
            'editable'          => $this->calc->isEditableMonth($dayStart, $now),
            'holiday'           => $isHoliday,
            'absence'           => $isAbsence,
            'absenceTypeName'   => $absenceInfo['typeName'] ?? null,
            'absenceTypeKey'    => $absenceInfo['typeKey'] ?? null,
            'workLocationName'  => $workLocation?->getName(),
            'workLocationIcon'  => $workLocation?->getIcon(),
        ];
    }

    private function extractDayStartEnd(
        array $dayEntries,
        DateTimeImmutable $dayStart,
        DateTimeImmutable $dayEnd,
        DateTimeImmutable $now,
    ): array {
        if (empty($dayEntries)) {
            return [null, null];
        }

        $startTimes = [];
        $endTimes   = [];

        foreach ($dayEntries as $e) {
            $s            = $e->getStartedAt();
            $startTimes[] = $s < $dayStart ? $dayStart : $s;
            $endTimes[]   = min($e->getStoppedAt() ?? $now, $dayEnd);
        }

        usort($startTimes, fn($a, $b) => $a <=> $b);
        usort($endTimes, fn($a, $b) => $a <=> $b);

        return [$startTimes[0], end($endTimes)];
    }

    private function buildRowClass(bool $isWorkday, bool $isHoliday, bool $isAbsence, bool $isEmpty): string
    {
        $class = 'tt-day ' . ($isWorkday ? 'tt-day--workday' : 'tt-day--offday');

        if ($isHoliday) $class .= ' tt-day--holiday';
        if ($isAbsence) $class .= ' tt-day--absence';
        if ($isEmpty)   $class .= ' tt-day--empty';

        return $class;
    }
}
