<?php

namespace App\Tests\Service;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\Holiday;
use App\Entity\Settings;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Entity\WorkLocationType;
use App\Repository\AbsenceRequestRepository;
use App\Repository\HolidayRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\WorkLocationRepository;
use App\Repository\WorkLocationTypeRepository;
use App\Service\TimeTrackingCalculator;
use App\Service\UserYearReportBuilder;
use App\Service\WorktimeSollCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Tests the work location resolution logic in UserYearReportBuilder::buildDayRow().
 *
 * All tests use 2024-01-15 (Monday) as the canonical workday
 * and 2024-01-13 (Saturday) as the canonical weekend day.
 */
final class UserYearReportBuilderWorkLocationTest extends TestCase
{
    private const WORKDAY   = '2024-01-15'; // Monday
    private const WEEKEND   = '2024-01-13'; // Saturday
    private const TEST_YEAR = 2024;

    // --- helpers ---

    private function makeLocationType(string $name, string $key = 'office'): WorkLocationType
    {
        $t = new WorkLocationType();
        $t->setName($name);
        $t->setKeyName($key);
        return $t;
    }

    private function makeUser(): User
    {
        $u = new User();
        $u->setDailyWorkMinutes(480);
        return $u;
    }

    private function makeSettings(): Settings
    {
        $s = new Settings();
        $s->setAutoPauseAfterSixHours(0);
        $s->setAutoPauseAfterNineHours(0);
        return $s;
    }

    private function makeEntry(string $date): TimeEntry
    {
        $e = new TimeEntry();
        $e->setStartedAt(new DateTimeImmutable($date . ' 08:00:00'));
        $e->setStoppedAt(new DateTimeImmutable($date . ' 16:00:00'));
        return $e;
    }

    private function makeHoliday(string $date): Holiday&Stub
    {
        $h = $this->createStub(Holiday::class);
        $h->method('getDate')->willReturn(new DateTimeImmutable($date));
        return $h;
    }

    private function makeApprovedAbsence(string $date): AbsenceRequest
    {
        $type = new AbsenceType();
        $type->setName('Urlaub');
        $type->setKeyName('vacation');

        $a = new AbsenceRequest();
        $a->setRequestedBy(new User());
        $a->setType($type);
        $a->setStartDate(new DateTimeImmutable($date));
        $a->setEndDate(new DateTimeImmutable($date));
        $a->setStatus(AbsenceRequest::STATUS_APPROVED);
        return $a;
    }

    private function makeBuilder(
        array $entries = [],
        array $holidays = [],
        array $absences = [],
        array $locationMap = [],
        ?WorkLocationType $defaultLocation = null,
    ): UserYearReportBuilder {
        $timeEntryRepo    = $this->createStub(TimeEntryRepository::class);
        $holidayRepo      = $this->createStub(HolidayRepository::class);
        $absenceRepo      = $this->createStub(AbsenceRequestRepository::class);
        $locationRepo     = $this->createStub(WorkLocationRepository::class);
        $locationTypeRepo = $this->createStub(WorkLocationTypeRepository::class);

        $timeEntryRepo->method('findForUserBetween')->willReturn($entries);
        $holidayRepo->method('findForUserBetween')->willReturn($holidays);
        $absenceRepo->method('findApprovedForUserBetween')->willReturn($absences);
        $locationRepo->method('buildTypeMapForUser')->willReturn($locationMap);
        $locationTypeRepo->method('findDefault')->willReturn($defaultLocation);

        // WorktimeSollCalculator is final — use the real instance with the stubbed repos
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

    // --- tests ---

    public function testExplicitLocation_workdayWithEntry_isShown(): void
    {
        $type    = $this->makeLocationType('Home Office', 'home_office');
        $builder = $this->makeBuilder(
            entries:     [$this->makeEntry(self::WORKDAY)],
            locationMap: [self::WORKDAY => $type],
        );

        $day = $this->findDay($builder->build($this->makeUser(), self::TEST_YEAR, $this->makeSettings()), self::WORKDAY);

        $this->assertSame('Home Office', $day['workLocationName']);
    }

    public function testExplicitLocation_workdayWithoutEntry_isStillShown(): void
    {
        // An explicitly set location is always shown, even if no time was booked.
        $type    = $this->makeLocationType('Geschäftsreise', 'business_trip');
        $builder = $this->makeBuilder(
            locationMap: [self::WORKDAY => $type],
        );

        $day = $this->findDay($builder->build($this->makeUser(), self::TEST_YEAR, $this->makeSettings()), self::WORKDAY);

        $this->assertSame('Geschäftsreise', $day['workLocationName']);
    }

    public function testNoExplicitLocation_workdayWithEntry_defaultIsShown(): void
    {
        $default = $this->makeLocationType('Büro', 'office');
        $builder = $this->makeBuilder(
            entries:         [$this->makeEntry(self::WORKDAY)],
            defaultLocation: $default,
        );

        $day = $this->findDay($builder->build($this->makeUser(), self::TEST_YEAR, $this->makeSettings()), self::WORKDAY);

        $this->assertSame('Büro', $day['workLocationName']);
    }

    public function testNoExplicitLocation_workdayWithoutEntry_locationIsNull(): void
    {
        $default = $this->makeLocationType('Büro', 'office');
        $builder = $this->makeBuilder(
            defaultLocation: $default,
        );

        $day = $this->findDay($builder->build($this->makeUser(), self::TEST_YEAR, $this->makeSettings()), self::WORKDAY);

        $this->assertNull($day['workLocationName']);
    }

    public function testNoExplicitLocation_weekend_locationIsNull(): void
    {
        $default = $this->makeLocationType('Büro', 'office');
        $builder = $this->makeBuilder(
            entries:         [$this->makeEntry(self::WEEKEND)],
            defaultLocation: $default,
        );

        $day = $this->findDay($builder->build($this->makeUser(), self::TEST_YEAR, $this->makeSettings()), self::WEEKEND);

        $this->assertNull($day['workLocationName']);
    }

    public function testNoExplicitLocation_holiday_locationIsNull(): void
    {
        $default = $this->makeLocationType('Büro', 'office');
        $builder = $this->makeBuilder(
            entries:         [$this->makeEntry(self::WORKDAY)],
            holidays:        [$this->makeHoliday(self::WORKDAY)],
            defaultLocation: $default,
        );

        $day = $this->findDay($builder->build($this->makeUser(), self::TEST_YEAR, $this->makeSettings()), self::WORKDAY);

        $this->assertNull($day['workLocationName']);
    }

    public function testNoExplicitLocation_absenceDay_locationIsNull(): void
    {
        $default = $this->makeLocationType('Büro', 'office');
        $builder = $this->makeBuilder(
            entries:         [$this->makeEntry(self::WORKDAY)],
            absences:        [$this->makeApprovedAbsence(self::WORKDAY)],
            defaultLocation: $default,
        );

        $day = $this->findDay($builder->build($this->makeUser(), self::TEST_YEAR, $this->makeSettings()), self::WORKDAY);

        $this->assertNull($day['workLocationName']);
    }

    public function testNoDefaultConfigured_workdayWithEntry_locationIsNull(): void
    {
        // No default type set in system → null even for regular workdays with entries.
        $builder = $this->makeBuilder(
            entries:         [$this->makeEntry(self::WORKDAY)],
            defaultLocation: null,
        );

        $day = $this->findDay($builder->build($this->makeUser(), self::TEST_YEAR, $this->makeSettings()), self::WORKDAY);

        $this->assertNull($day['workLocationName']);
    }

    public function testWorkLocationIcon_passedThroughFromExplicitType(): void
    {
        $type = $this->makeLocationType('Home Office', 'home_office');
        $type->setIcon('fa-solid fa-house');

        $builder = $this->makeBuilder(
            entries:     [$this->makeEntry(self::WORKDAY)],
            locationMap: [self::WORKDAY => $type],
        );

        $day = $this->findDay($builder->build($this->makeUser(), self::TEST_YEAR, $this->makeSettings()), self::WORKDAY);

        $this->assertSame('fa-solid fa-house', $day['workLocationIcon']);
    }

    public function testWorkLocationIcon_nullWhenTypeHasNoIcon(): void
    {
        $type    = $this->makeLocationType('Büro', 'office'); // no icon set
        $builder = $this->makeBuilder(
            entries:     [$this->makeEntry(self::WORKDAY)],
            locationMap: [self::WORKDAY => $type],
        );

        $day = $this->findDay($builder->build($this->makeUser(), self::TEST_YEAR, $this->makeSettings()), self::WORKDAY);

        $this->assertNull($day['workLocationIcon']);
    }
}
