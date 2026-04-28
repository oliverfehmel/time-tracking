<?php

namespace App\Tests\Service;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\AbsenceRequestRepository;
use App\Repository\HolidayRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\WorkLocationRepository;
use App\Repository\WorkLocationTypeRepository;
use App\Service\TimeTrackingCalculator;
use App\Service\UserYearReportBuilder;
use App\Service\WorktimeSollCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Tests that Soll hours are capped to today so that future workdays
 * do not create phantom debt in the delta.
 */
final class UserYearReportBuilderDeltaCapTest extends TestCase
{
    // All days are in the past relative to any run after 2024.
    private const PAST_YEAR    = 2024;
    private const PAST_WORKDAY = '2024-01-15'; // Monday
    private const PAST_MONTH   = 1;            // January 2024 has 23 workdays

    // All days are in the future relative to any run before 2099.
    // 2099-01-01 is a Thursday, so the first Monday is 2099-01-05.
    private const FUTURE_YEAR    = 2099;
    private const FUTURE_WORKDAY = '2099-01-05'; // Monday
    private const FUTURE_MONTH   = 1;

    private const DAILY_MINUTES = 480; // 8 h

    // --- helpers ---

    private function makeUser(): User
    {
        $u = new User();
        $u->setDailyWorkMinutes(self::DAILY_MINUTES);
        return $u;
    }

    private function makeSettings(): Settings
    {
        $s = new Settings();
        $s->setAutoPauseAfterSixHours(0);
        $s->setAutoPauseAfterNineHours(0);
        return $s;
    }

    private function makeBuilder(): UserYearReportBuilder
    {
        $timeEntryRepo    = $this->createStub(TimeEntryRepository::class);
        $holidayRepo      = $this->createStub(HolidayRepository::class);
        $absenceRepo      = $this->createStub(AbsenceRequestRepository::class);
        $locationRepo     = $this->createStub(WorkLocationRepository::class);
        $locationTypeRepo = $this->createStub(WorkLocationTypeRepository::class);

        $timeEntryRepo->method('findForUserBetween')->willReturn([]);
        $holidayRepo->method('findForUserBetween')->willReturn([]);
        $absenceRepo->method('findApprovedForUserBetween')->willReturn([]);
        $locationRepo->method('buildTypeMapForUser')->willReturn([]);
        $locationTypeRepo->method('findDefault')->willReturn(null);

        $sollCalc = new WorktimeSollCalculator($holidayRepo, $absenceRepo);

        return new UserYearReportBuilder(
            $timeEntryRepo,
            $holidayRepo,
            $absenceRepo,
            $locationRepo,
            $locationTypeRepo,
            new TimeTrackingCalculator(),
            $sollCalc,
        );
    }

    private function findDay(array $months, string $date): array
    {
        foreach ($months as $m) {
            foreach ($m['days'] as $d) {
                if ($d['date']->format('Y-m-d') === $date) {
                    return $d;
                }
            }
        }
        $this->fail("Day {$date} not found in report.");
    }

    private function findMonth(array $months, int $monthNumber): array
    {
        foreach ($months as $m) {
            if ((int) $m['start']->format('n') === $monthNumber) {
                return $m;
            }
        }
        $this->fail("Month {$monthNumber} not found in report.");
    }

    // --- tests ---

    public function testPastWorkday_noEntries_deltaEqualsFullDaySoll(): void
    {
        $day = $this->findDay(
            $this->makeBuilder()->build($this->makeUser(), self::PAST_YEAR, $this->makeSettings()),
            self::PAST_WORKDAY,
        );

        $this->assertSame(-(self::DAILY_MINUTES * 60), $day['deltaSeconds']);
    }

    public function testFutureWorkday_noEntries_deltaIsZero(): void
    {
        // Soll must be 0 for days that have not happened yet — no debt can accrue.
        $day = $this->findDay(
            $this->makeBuilder()->build($this->makeUser(), self::FUTURE_YEAR, $this->makeSettings()),
            self::FUTURE_WORKDAY,
        );

        $this->assertSame(0, $day['deltaSeconds']);
    }

    public function testFutureMonth_noEntries_monthDeltaIsZero(): void
    {
        $month = $this->findMonth(
            $this->makeBuilder()->build($this->makeUser(), self::FUTURE_YEAR, $this->makeSettings()),
            self::FUTURE_MONTH,
        );

        $this->assertSame(0, $month['deltaSeconds']);
    }

    public function testFutureMonth_sollFormattedAsZero(): void
    {
        $month = $this->findMonth(
            $this->makeBuilder()->build($this->makeUser(), self::FUTURE_YEAR, $this->makeSettings()),
            self::FUTURE_MONTH,
        );

        $this->assertSame('0:00', $month['soll']);
    }

    public function testPastMonth_noEntries_monthDeltaEqualsAllWorkdaySoll(): void
    {
        // January 2024 (Mon 1 Jan) has 23 workdays: 5+5+5+5+3.
        $month = $this->findMonth(
            $this->makeBuilder()->build($this->makeUser(), self::PAST_YEAR, $this->makeSettings()),
            self::PAST_MONTH,
        );

        $this->assertSame(-(23 * self::DAILY_MINUTES * 60), $month['deltaSeconds']);
    }
}
