<?php

namespace App\Tests\Service;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\Holiday;
use App\Entity\User;
use App\Repository\AbsenceRequestRepository;
use App\Repository\HolidayRepository;
use App\Service\WorktimeSollCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class WorktimeSollCalculatorTest extends TestCase
{
    private HolidayRepository&Stub $holidayRepo;
    private AbsenceRequestRepository&Stub $absenceRepo;
    private WorktimeSollCalculator $calc;

    protected function setUp(): void
    {
        $this->holidayRepo = $this->createStub(HolidayRepository::class);
        $this->absenceRepo = $this->createStub(AbsenceRequestRepository::class);
        $this->calc        = new WorktimeSollCalculator($this->holidayRepo, $this->absenceRepo);
    }

    private function makeUser(int $dailyMinutes = 480): User
    {
        $user = new User();
        $user->setDailyWorkMinutes($dailyMinutes);
        return $user;
    }

    public function testSoll_fullWorkweekMonFri_fiveWorkdays(): void
    {
        // 2024-01-15 = Monday, 2024-01-22 = Monday (exclusive)
        $from = new DateTimeImmutable('2024-01-15 00:00:00');
        $to   = new DateTimeImmutable('2024-01-22 00:00:00');
        $user = $this->makeUser(480);

        $this->holidayRepo->method('findForUserBetween')->willReturn([]);
        $this->absenceRepo->method('findApprovedForUserBetween')->willReturn([]);

        $this->assertSame(5 * 8 * 3600, $this->calc->sollSecondsForRange($user, $from, $to));
    }

    public function testSoll_weekendOnlyRange_returnsZero(): void
    {
        // 2024-01-13 = Saturday, 2024-01-15 = Monday (exclusive)
        $from = new DateTimeImmutable('2024-01-13 00:00:00');
        $to   = new DateTimeImmutable('2024-01-15 00:00:00');
        $user = $this->makeUser(480);

        $this->holidayRepo->method('findForUserBetween')->willReturn([]);
        $this->absenceRepo->method('findApprovedForUserBetween')->willReturn([]);

        $this->assertSame(0, $this->calc->sollSecondsForRange($user, $from, $to));
    }

    public function testSoll_userHolidayExcluded(): void
    {
        // Mon–Fri, one holiday on Wednesday
        $from    = new DateTimeImmutable('2024-01-15 00:00:00'); // Monday
        $to      = new DateTimeImmutable('2024-01-22 00:00:00');
        $user    = $this->makeUser(480);
        $holiday = $this->makeHoliday('2024-01-17'); // Wednesday

        $this->holidayRepo->method('findForUserBetween')->willReturn([$holiday]);
        $this->absenceRepo->method('findApprovedForUserBetween')->willReturn([]);

        // 5 workdays - 1 holiday = 4 days × 8h
        $this->assertSame(4 * 8 * 3600, $this->calc->sollSecondsForRange($user, $from, $to));
    }

    public function testSoll_approvedAbsenceExcluded(): void
    {
        // Mon–Fri, one approved absence covering Tuesday–Wednesday
        $from    = new DateTimeImmutable('2024-01-15 00:00:00'); // Monday
        $to      = new DateTimeImmutable('2024-01-22 00:00:00');
        $user    = $this->makeUser(480);
        $absence = $this->makeApprovedAbsence('2024-01-16', '2024-01-17'); // Tue + Wed

        $this->holidayRepo->method('findForUserBetween')->willReturn([]);
        $this->absenceRepo->method('findApprovedForUserBetween')->willReturn([$absence]);

        // 5 workdays - 2 absence days = 3 days × 8h
        $this->assertSame(3 * 8 * 3600, $this->calc->sollSecondsForRange($user, $from, $to));
    }

    public function testSoll_holidayAndAbsenceOnSameDay_countedOnce(): void
    {
        $from    = new DateTimeImmutable('2024-01-15 00:00:00'); // Monday
        $to      = new DateTimeImmutable('2024-01-22 00:00:00');
        $user    = $this->makeUser(480);
        $holiday = $this->makeHoliday('2024-01-15'); // Monday is holiday
        $absence = $this->makeApprovedAbsence('2024-01-15', '2024-01-15'); // also absence

        $this->holidayRepo->method('findForUserBetween')->willReturn([$holiday]);
        $this->absenceRepo->method('findApprovedForUserBetween')->willReturn([$absence]);

        // Monday excluded once, Tue–Fri = 4 days
        $this->assertSame(4 * 8 * 3600, $this->calc->sollSecondsForRange($user, $from, $to));
    }

    public function testSoll_zeroDailyWorkMinutes_returnsZero(): void
    {
        $from = new DateTimeImmutable('2024-01-15');
        $to   = new DateTimeImmutable('2024-01-22');
        $user = $this->makeUser(0);

        // Use mocks (not stubs) to assert the repos are never queried
        $holidayRepo = $this->createMock(HolidayRepository::class);
        $holidayRepo->expects($this->never())->method('findForUserBetween');

        $absenceRepo = $this->createMock(AbsenceRequestRepository::class);
        $absenceRepo->expects($this->never())->method('findApprovedForUserBetween');

        $calc = new WorktimeSollCalculator($holidayRepo, $absenceRepo);

        $this->assertSame(0, $calc->sollSecondsForRange($user, $from, $to));
    }

    public function testSoll_absenceClampedToRange(): void
    {
        // Absence spans beyond the range — all workdays should be excluded
        $from    = new DateTimeImmutable('2024-01-15 00:00:00'); // Monday
        $to      = new DateTimeImmutable('2024-01-22 00:00:00');
        $user    = $this->makeUser(480);
        $absence = $this->makeApprovedAbsence('2024-01-10', '2024-01-30'); // spans whole range

        $this->holidayRepo->method('findForUserBetween')->willReturn([]);
        $this->absenceRepo->method('findApprovedForUserBetween')->willReturn([$absence]);

        // All 5 workdays are within the absence → 0
        $this->assertSame(0, $this->calc->sollSecondsForRange($user, $from, $to));
    }

    // --- Helpers ---

    private function makeHoliday(string $date): Holiday&Stub
    {
        $holiday = $this->createStub(Holiday::class);
        $holiday->method('getDate')->willReturn(new DateTimeImmutable($date));
        return $holiday;
    }

    private function makeApprovedAbsence(string $start, string $end): AbsenceRequest
    {
        $type = new AbsenceType();
        $type->setName('Urlaub');
        $type->setKeyName('vacation');

        $absence = new AbsenceRequest();
        $absence->setRequestedBy(new User());
        $absence->setType($type);
        $absence->setStartDate(new DateTimeImmutable($start));
        $absence->setEndDate(new DateTimeImmutable($end));
        $absence->setStatus(AbsenceRequest::STATUS_APPROVED);

        return $absence;
    }
}
