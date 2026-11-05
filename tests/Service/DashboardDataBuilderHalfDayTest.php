<?php

namespace App\Tests\Service;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\Holiday;
use App\Entity\Settings;
use App\Entity\User;
use App\Repository\AbsenceQuotaRepository;
use App\Repository\AbsenceRequestRepository;
use App\Repository\AbsenceTypeRepository;
use App\Repository\HolidayRepository;
use App\Repository\SettingsRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\WorkLocationRepository;
use App\Repository\WorkLocationTypeRepository;
use App\Service\AbsenceDayCalculator;
use App\Service\DashboardDataBuilder;
use App\Service\TimeTrackingCalculator;
use App\Service\WorktimeSollCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Absence summaries on the dashboard count half days and half-day holidays as 0.5.
 */
final class DashboardDataBuilderHalfDayTest extends TestCase
{
    public function testAbsenceSummaries_countHalfDaysAndHalfHolidays(): void
    {
        // A Monday in the current year (the dashboard always shows the current year)
        $monday = new DateTimeImmutable('first monday of january this year');
        if ((int) $monday->format('j') < 2) {
            $monday = $monday->modify('+7 days'); // stay clear of New Year's Day edge cases
        }
        $tuesday   = $monday->modify('+1 day');
        $wednesday = $monday->modify('+2 days');

        $type = new AbsenceType();
        $type->setName('Urlaub');
        $type->setKeyName('vacation');
        $type->setRequiresQuota(true);
        $type->setDefaultYearlyQuotaDays(30);
        (new ReflectionClass($type))->getProperty('id')->setValue($type, 1);

        $user = new User();

        $fullMonTue = $this->makeRequest($user, $type, $monday, $tuesday, AbsenceRequest::STATUS_APPROVED);
        $halfWed    = $this->makeRequest($user, $type, $wednesday, $wednesday, AbsenceRequest::STATUS_PENDING)->setHalfDay(true);

        $halfHoliday = (new Holiday())->setName('Halber Feiertag')->setDate($tuesday)->setHalfDay(true);

        $summaries = $this->makeBuilder([$type], [$fullMonTue, $halfWed], [$halfHoliday])->build($user)['absenceSummaries'];

        $this->assertCount(1, $summaries);
        $this->assertSame(1.5, $summaries[0]['approvedDays']);   // Mon 1 + Tue (half holiday) 0.5
        $this->assertSame(0.5, $summaries[0]['pendingDays']);    // half day
        $this->assertSame(28.5, $summaries[0]['remainingDays']); // 30 - 1.5
    }

    private function makeRequest(User $user, AbsenceType $type, DateTimeImmutable $start, DateTimeImmutable $end, string $status): AbsenceRequest
    {
        return (new AbsenceRequest())
            ->setRequestedBy($user)
            ->setType($type)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setStatus($status);
    }

    private function makeBuilder(array $types, array $requests, array $holidays): DashboardDataBuilder
    {
        $timeEntryRepo      = $this->createStub(TimeEntryRepository::class);
        $absenceTypeRepo    = $this->createStub(AbsenceTypeRepository::class);
        $absenceRequestRepo = $this->createStub(AbsenceRequestRepository::class);
        $absenceQuotaRepo   = $this->createStub(AbsenceQuotaRepository::class);
        $holidayRepo        = $this->createStub(HolidayRepository::class);
        $settingsRepo       = $this->createStub(SettingsRepository::class);
        $workLocationRepo   = $this->createStub(WorkLocationRepository::class);
        $workLocationTypeRepo = $this->createStub(WorkLocationTypeRepository::class);

        $timeEntryRepo->method('findForUserBetween')->willReturn([]);
        $timeEntryRepo->method('findRunningForUser')->willReturn(null);
        $absenceTypeRepo->method('findActive')->willReturn($types);
        $absenceRequestRepo->method('findForUserOverlappingRangeWithStatuses')->willReturn($requests);
        $absenceRequestRepo->method('findForUserOverlappingRangeAllTypes')->willReturn([]);
        $absenceRequestRepo->method('findApprovedForUserBetween')->willReturn([]);
        $absenceQuotaRepo->method('findForUserYear')->willReturn([]);
        $holidayRepo->method('findForUsersInMonth')->willReturn($holidays);
        $holidayRepo->method('findForUserBetween')->willReturn([]);
        $settingsRepo->method('getOrCreate')->willReturn(new Settings());
        $workLocationTypeRepo->method('findActive')->willReturn([]);

        return new DashboardDataBuilder(
            $timeEntryRepo,
            new TimeTrackingCalculator(),
            new WorktimeSollCalculator($holidayRepo, $absenceRequestRepo),
            $absenceTypeRepo,
            $absenceRequestRepo,
            $absenceQuotaRepo,
            $holidayRepo,
            new AbsenceDayCalculator($holidayRepo),
            $settingsRepo,
            $workLocationRepo,
            $workLocationTypeRepo,
        );
    }
}
