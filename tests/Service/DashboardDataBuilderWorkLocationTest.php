<?php

namespace App\Tests\Service;

use App\Entity\Settings;
use App\Entity\User;
use App\Entity\WorkLocation;
use App\Entity\WorkLocationType;
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the work location integration in DashboardDataBuilder::build().
 *
 * Verifies that the three location keys — locationTypes, todayWorkLocation,
 * defaultLocationType — are correctly populated or skipped based on whether
 * active location types exist.
 */
final class DashboardDataBuilderWorkLocationTest extends TestCase
{
    // --- tests ---

    public function testBuild_withActiveLocationTypes_locationTypesAreInResult(): void
    {
        $type    = $this->makeLocationType('Home Office');
        $builder = $this->makeBuilder(activeLocationTypes: [$type]);

        $result = $builder->build($this->makeUser());

        $this->assertSame([$type], $result['locationTypes']);
    }

    public function testBuild_withActiveLocationTypes_todayWorkLocationIsReturnedFromRepo(): void
    {
        $type         = $this->makeLocationType('Büro');
        $workLocation = $this->makeWorkLocation($type);
        $builder      = $this->makeBuilder(
            activeLocationTypes: [$type],
            todayWorkLocation:   $workLocation,
        );

        $result = $builder->build($this->makeUser());

        $this->assertSame($workLocation, $result['todayWorkLocation']);
    }

    public function testBuild_withActiveLocationTypes_defaultLocationTypeIsReturnedFromRepo(): void
    {
        $type        = $this->makeLocationType('Büro');
        $defaultType = $this->makeLocationType('Büro');
        $builder     = $this->makeBuilder(
            activeLocationTypes: [$type],
            defaultLocationType: $defaultType,
        );

        $result = $builder->build($this->makeUser());

        $this->assertSame($defaultType, $result['defaultLocationType']);
    }

    public function testBuild_withActiveLocationTypes_nullWorkLocationPassedThrough(): void
    {
        $type    = $this->makeLocationType('Büro');
        $builder = $this->makeBuilder(
            activeLocationTypes: [$type],
            todayWorkLocation:   null,
        );

        $result = $builder->build($this->makeUser());

        $this->assertNull($result['todayWorkLocation']);
    }

    public function testBuild_withNoActiveLocationTypes_locationKeysAreNull(): void
    {
        $builder = $this->makeBuilder(activeLocationTypes: []);

        $result = $builder->build($this->makeUser());

        $this->assertSame([], $result['locationTypes']);
        $this->assertNull($result['todayWorkLocation']);
        $this->assertNull($result['defaultLocationType']);
    }

    public function testBuild_withNoActiveLocationTypes_skipsLocationRepositoryQueries(): void
    {
        $workLocationTypeRepo = $this->createMock(WorkLocationTypeRepository::class);
        $workLocationTypeRepo->method('findActive')->willReturn([]);
        $workLocationTypeRepo->expects($this->never())->method('findDefault');

        $workLocationRepo = $this->createMock(WorkLocationRepository::class);
        $workLocationRepo->expects($this->never())->method('findForUserOnDate');

        $builder = $this->makeBuilder(
            workLocationRepo:     $workLocationRepo,
            workLocationTypeRepo: $workLocationTypeRepo,
        );

        $builder->build($this->makeUser());
    }

    // --- helpers ---

    private function makeUser(): User
    {
        $u = new User();
        $u->setDailyWorkMinutes(0);
        return $u;
    }

    private function makeLocationType(string $name): WorkLocationType
    {
        $t = new WorkLocationType();
        $t->setName($name);
        $t->setKeyName(strtolower(str_replace(' ', '_', $name)));
        return $t;
    }

    private function makeWorkLocation(WorkLocationType $type): WorkLocation
    {
        $l = new WorkLocation();
        $l->setUser(new User());
        $l->setDate(new DateTimeImmutable('today'));
        $l->setLocationType($type);
        return $l;
    }

    private function makeBuilder(
        array $activeLocationTypes = [],
        ?WorkLocation $todayWorkLocation = null,
        ?WorkLocationType $defaultLocationType = null,
        ?WorkLocationRepository $workLocationRepo = null,
        ?WorkLocationTypeRepository $workLocationTypeRepo = null,
    ): DashboardDataBuilder {
        $timeEntryRepo      = $this->createStub(TimeEntryRepository::class);
        $absenceTypeRepo    = $this->createStub(AbsenceTypeRepository::class);
        $absenceRequestRepo = $this->createStub(AbsenceRequestRepository::class);
        $absenceQuotaRepo   = $this->createStub(AbsenceQuotaRepository::class);
        $holidayRepo        = $this->createStub(HolidayRepository::class);
        $settingsRepo       = $this->createStub(SettingsRepository::class);

        $settings = new Settings();
        $settings->setAutoPauseAfterSixHours(0);
        $settings->setAutoPauseAfterNineHours(0);

        $timeEntryRepo->method('findForUserBetween')->willReturn([]);
        $timeEntryRepo->method('findRunningForUser')->willReturn(null);
        $absenceTypeRepo->method('findActive')->willReturn([]);
        $absenceRequestRepo->method('findForUserOverlappingRangeWithStatuses')->willReturn([]);
        $absenceRequestRepo->method('findForUserOverlappingRangeAllTypes')->willReturn([]);
        $absenceRequestRepo->method('findApprovedForUserBetween')->willReturn([]);
        $absenceQuotaRepo->method('findForUserYear')->willReturn([]);
        $holidayRepo->method('findForUsersInMonth')->willReturn([]);
        $holidayRepo->method('findForUserBetween')->willReturn([]);
        $settingsRepo->method('getOrCreate')->willReturn($settings);

        if ($workLocationTypeRepo === null) {
            $workLocationTypeRepo = $this->createStub(WorkLocationTypeRepository::class);
            $workLocationTypeRepo->method('findActive')->willReturn($activeLocationTypes);
            $workLocationTypeRepo->method('findDefault')->willReturn($defaultLocationType);
        }

        if ($workLocationRepo === null) {
            $workLocationRepo = $this->createStub(WorkLocationRepository::class);
            $workLocationRepo->method('findForUserOnDate')->willReturn($todayWorkLocation);
        }

        // WorktimeSollCalculator is final — use the real instance with stubbed repos
        $sollCalc = new WorktimeSollCalculator($holidayRepo, $absenceRequestRepo);

        return new DashboardDataBuilder(
            $timeEntryRepo,
            new TimeTrackingCalculator(),
            $sollCalc,
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
