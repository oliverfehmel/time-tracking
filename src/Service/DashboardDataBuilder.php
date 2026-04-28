<?php

// src/Service/DashboardDataBuilder.php
namespace App\Service;

use App\Entity\AbsenceRequest;
use App\Entity\Settings;
use App\Entity\User;
use App\Repository\AbsenceQuotaRepository;
use App\Repository\AbsenceRequestRepository;
use App\Repository\AbsenceTypeRepository;
use App\Repository\HolidayRepository;
use App\Repository\SettingsRepository;
use App\Repository\TimeEntryRepository;
use DateTimeImmutable;

final class DashboardDataBuilder
{
    public function __construct(
        private readonly TimeEntryRepository $timeEntryRepo,
        private readonly TimeTrackingCalculator $calc,
        private readonly WorktimeSollCalculator $sollCalc,
        private readonly AbsenceTypeRepository $absenceTypeRepo,
        private readonly AbsenceRequestRepository $absenceRequestRepo,
        private readonly AbsenceQuotaRepository $absenceQuotaRepo,
        private readonly HolidayRepository $holidayRepo,
        private readonly AbsenceDayCalculator $dayCalc,
        private readonly SettingsRepository $settingsRepo,
    ) {}

    public function build(User $user): array
    {
        $settings = $this->settingsRepo->getOrCreate();
        $now      = new DateTimeImmutable('now');
        $today    = $now->setTime(0, 0);

        $timeData    = $this->buildTimeData($user, $now, $today, $settings);
        $absenceData = $this->buildAbsenceData($user, $now, $today);

        return array_merge($timeData, $absenceData);
    }

    private function buildTimeData(User $user, DateTimeImmutable $now, DateTimeImmutable $today, Settings $settings): array
    {
        $tomorrowStart = $today->modify('+1 day');
        $weekStart     = $today->modify('monday this week');
        $weekEnd       = $weekStart->modify('+7 days');
        $monthStart    = $today->modify('first day of this month');
        $monthEnd      = $monthStart->modify('+1 month');

        $todayEntries = $this->timeEntryRepo->findForUserBetween($user, $today, $tomorrowStart);
        $todayRaw     = $this->calc->workedSecondsInRange($todayEntries, $today, $tomorrowStart, $now);
        $todayNet     = $this->calc->applyDailyBreakRuleSeconds($todayRaw, $settings);

        $weekEntries  = $this->timeEntryRepo->findForUserBetween($user, $weekStart, $weekEnd);
        $monthEntries = $this->timeEntryRepo->findForUserBetween($user, $monthStart, $monthEnd);

        $weekNet  = $this->calc->sumNetSecondsPerDay($weekEntries, $weekStart, $weekEnd, $now, $settings);
        $monthNet = $this->calc->sumNetSecondsPerDay($monthEntries, $monthStart, $monthEnd, $now, $settings);

        $tomorrow     = $today->modify('+1 day');
        $weekSollEnd  = $weekEnd <= $tomorrow ? $weekEnd : $tomorrow;
        $monthSollEnd = $monthEnd <= $tomorrow ? $monthEnd : $tomorrow;

        return [
            'running'     => $this->timeEntryRepo->findRunningForUser($user),
            'todayWorked' => $this->calc->formatSeconds($todayNet),
            'weekIst'     => $this->calc->formatSeconds($weekNet),
            'weekSoll'    => $this->calc->formatSeconds($this->sollCalc->sollSecondsForRange($user, $weekStart, $weekSollEnd)),
            'monthIst'    => $this->calc->formatSeconds($monthNet),
            'monthSoll'   => $this->calc->formatSeconds($this->sollCalc->sollSecondsForRange($user, $monthStart, $monthSollEnd)),
        ];
    }

    private function buildAbsenceData(User $user, DateTimeImmutable $now, DateTimeImmutable $today): array
    {
        $year          = (int) $now->format('Y');
        $yearStart     = new DateTimeImmutable("$year-01-01")->setTime(0, 0);
        $nextYearStart = new DateTimeImmutable(($year + 1) . "-01-01")->setTime(0, 0);

        $holidayDates = $this->buildHolidayDates($user, $yearStart, $nextYearStart);
        $summaries    = $this->buildAbsenceSummaries($user, $year, $yearStart, $nextYearStart, $holidayDates);
        $navigation   = $this->buildAbsenceNavigation($user, $today);

        return array_merge(
            ['absenceSummaries' => $summaries, 'absenceRequestPending' => AbsenceRequest::STATUS_PENDING],
            $navigation,
        );
    }

    private function buildHolidayDates(User $user, DateTimeImmutable $yearStart, DateTimeImmutable $nextYearStart): array
    {
        $dates = [];
        foreach ($this->holidayRepo->findForUsersInMonth([$user->getId()], $yearStart, $nextYearStart) as $h) {
            $dates[$h->getDate()->format('Y-m-d')] = true;
        }
        return $dates;
    }

    private function buildAbsenceSummaries(
        User $user,
        int $year,
        DateTimeImmutable $yearStart,
        DateTimeImmutable $nextYearStart,
        array $holidayDates,
    ): array {
        $types      = $this->absenceTypeRepo->findActive();
        $quotaRows  = $this->absenceQuotaRepo->findForUserYear($user, $year);

        $quotaByTypeId = [];
        foreach ($quotaRows as $q) {
            $quotaByTypeId[$q->getType()->getId()] = $q;
        }

        $summaries = [];
        foreach ($types as $t) {
            $typeId    = $t->getId();
            $quotaDays = null;

            if ($t->requiresQuota()) {
                $quota     = $quotaByTypeId[$typeId] ?? null;
                $quotaDays = $quota?->getQuotaDays() ?? $t->getDefaultYearlyQuotaDays();
            }

            $summaries[$typeId] = [
                'type'          => $t,
                'quotaDays'     => $quotaDays,
                'approvedDays'  => 0,
                'pendingDays'   => 0,
                'remainingDays' => null,
            ];
        }

        $requests = $this->absenceRequestRepo->findForUserOverlappingRangeWithStatuses(
            $user, $yearStart, $nextYearStart,
            [AbsenceRequest::STATUS_APPROVED, AbsenceRequest::STATUS_PENDING],
        );

        foreach ($requests as $r) {
            $typeId = $r->getType()->getId();
            if (!isset($summaries[$typeId])) {
                continue;
            }
            $days = $this->dayCalc->countWorkdaysForRequest($r, $yearStart, $nextYearStart, $holidayDates);

            if ($r->getStatus() === AbsenceRequest::STATUS_APPROVED) {
                $summaries[$typeId]['approvedDays'] += $days;
            } else {
                $summaries[$typeId]['pendingDays'] += $days;
            }
        }

        foreach ($summaries as &$s) {
            if ($s['quotaDays'] !== null) {
                $s['remainingDays'] = max(0, $s['quotaDays'] - $s['approvedDays']);
            }
        }
        unset($s);

        return array_values($summaries);
    }

    private function buildAbsenceNavigation(User $user, DateTimeImmutable $today): array
    {
        $requests = $this->absenceRequestRepo->findForUserOverlappingRangeAllTypes(
            $user,
            $today->modify('-120 days'),
            $today->modify('+366 days'),
            [AbsenceRequest::STATUS_APPROVED, AbsenceRequest::STATUS_PENDING],
        );

        $last    = null;
        $next    = null;
        $current = null;

        foreach ($requests as $r) {
            if ($r->getEndDate() < $today) {
                if ($last === null || $r->getEndDate() > $last->getEndDate()) {
                    $last = $r;
                }
            } elseif ($r->getStartDate() >= $today) {
                if ($next === null || $r->getStartDate() < $next->getStartDate()) {
                    $next = $r;
                }
            } elseif ($current === null) {
                $current = $r;
            }
        }

        return [
            'lastAbsence'    => $last,
            'nextAbsence'    => $next,
            'currentAbsence' => $current,
        ];
    }
}
