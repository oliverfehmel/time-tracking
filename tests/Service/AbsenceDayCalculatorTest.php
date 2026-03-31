<?php

namespace App\Tests\Service;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\Holiday;
use App\Entity\User;
use App\Repository\HolidayRepository;
use App\Service\AbsenceDayCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class AbsenceDayCalculatorTest extends TestCase
{
    private HolidayRepository&Stub $holidayRepo;
    private AbsenceDayCalculator $calc;

    protected function setUp(): void
    {
        $this->holidayRepo = $this->createStub(HolidayRepository::class);
        $this->calc        = new AbsenceDayCalculator($this->holidayRepo);
    }

    // --- countChargeableDays ---

    public function testCountChargeableDays_fullWorkweek_fiveDays(): void
    {
        $this->holidayRepo->method('findForUserBetween')->willReturn([]);

        $start = new DateTimeImmutable('2024-01-15'); // Monday
        $end   = new DateTimeImmutable('2024-01-19'); // Friday

        $this->assertSame(5, $this->calc->countChargeableDays($start, $end));
    }

    public function testCountChargeableDays_weekendExcluded(): void
    {
        $start = new DateTimeImmutable('2024-01-13'); // Saturday
        $end   = new DateTimeImmutable('2024-01-14'); // Sunday

        $this->assertSame(0, $this->calc->countChargeableDays($start, $end));
    }

    public function testCountChargeableDays_weekSpansWeekend_countsFiveWorkdays(): void
    {
        $this->holidayRepo->method('findForUserBetween')->willReturn([]);

        $start = new DateTimeImmutable('2024-01-15'); // Monday
        $end   = new DateTimeImmutable('2024-01-21'); // Sunday

        $this->assertSame(5, $this->calc->countChargeableDays($start, $end));
    }

    public function testCountChargeableDays_endBeforeStart_returnsZero(): void
    {
        $start = new DateTimeImmutable('2024-01-20');
        $end   = new DateTimeImmutable('2024-01-15');

        $this->assertSame(0, $this->calc->countChargeableDays($start, $end));
    }

    public function testCountChargeableDays_singleWorkday_returnsOne(): void
    {
        $this->holidayRepo->method('findForUserBetween')->willReturn([]);

        $day = new DateTimeImmutable('2024-01-15'); // Monday

        $this->assertSame(1, $this->calc->countChargeableDays($day, $day));
    }

    public function testCountChargeableDays_userHolidayExcluded(): void
    {
        $user    = new User();
        $holiday = $this->makeHoliday('2024-01-17'); // Wednesday

        $this->holidayRepo->method('findForUserBetween')->willReturn([$holiday]);

        $start = new DateTimeImmutable('2024-01-15'); // Monday
        $end   = new DateTimeImmutable('2024-01-19'); // Friday

        // 5 workdays - 1 holiday = 4
        $this->assertSame(4, $this->calc->countChargeableDays($start, $end, user: $user));
    }

    public function testCountChargeableDays_noUserProvided_holidayRepoNotCalled(): void
    {
        // Use a mock (not stub) here so we can assert the repo is never called
        $repo = $this->createMock(HolidayRepository::class);
        $repo->expects($this->never())->method('findForUserBetween');
        $calc = new AbsenceDayCalculator($repo);

        $start = new DateTimeImmutable('2024-01-15');
        $end   = new DateTimeImmutable('2024-01-19');

        $this->assertSame(5, $calc->countChargeableDays($start, $end));
    }

    public function testCountChargeableDays_twoWeeks_tenWorkdays(): void
    {
        $this->holidayRepo->method('findForUserBetween')->willReturn([]);

        $start = new DateTimeImmutable('2024-01-15'); // Monday
        $end   = new DateTimeImmutable('2024-01-26'); // Friday

        $this->assertSame(10, $this->calc->countChargeableDays($start, $end));
    }

    // --- countWorkdaysForRequest ---

    public function testCountWorkdaysForRequest_withinRange(): void
    {
        $request = $this->makeRequest('2024-01-15', '2024-01-19'); // Mon–Fri

        $rangeStart        = new DateTimeImmutable('2024-01-01');
        $rangeEndExclusive = new DateTimeImmutable('2024-02-01');

        $this->assertSame(5, $this->calc->countWorkdaysForRequest($request, $rangeStart, $rangeEndExclusive, []));
    }

    public function testCountWorkdaysForRequest_clampedToRangeStart(): void
    {
        // Request starts before range
        $request = $this->makeRequest('2024-01-10', '2024-01-19');

        $rangeStart        = new DateTimeImmutable('2024-01-15'); // Monday — clamps start
        $rangeEndExclusive = new DateTimeImmutable('2024-02-01');

        // Only Mon 15.01 – Fri 19.01 = 5 days
        $this->assertSame(5, $this->calc->countWorkdaysForRequest($request, $rangeStart, $rangeEndExclusive, []));
    }

    public function testCountWorkdaysForRequest_holidayExcluded(): void
    {
        $request = $this->makeRequest('2024-01-15', '2024-01-19'); // Mon–Fri

        $rangeStart        = new DateTimeImmutable('2024-01-01');
        $rangeEndExclusive = new DateTimeImmutable('2024-02-01');

        $holidayDates = ['2024-01-17' => true]; // Wednesday is a holiday

        $this->assertSame(4, $this->calc->countWorkdaysForRequest($request, $rangeStart, $rangeEndExclusive, $holidayDates));
    }

    // --- Helpers ---

    private function makeHoliday(string $date): Holiday&Stub
    {
        $holiday = $this->createStub(Holiday::class);
        $holiday->method('getDate')->willReturn(new DateTimeImmutable($date));
        return $holiday;
    }

    private function makeRequest(string $start, string $end): AbsenceRequest
    {
        $type = new AbsenceType();
        $type->setName('Urlaub');
        $type->setKeyName('vacation');

        $request = new AbsenceRequest();
        $request->setRequestedBy(new User());
        $request->setType($type);
        $request->setStartDate(new DateTimeImmutable($start));
        $request->setEndDate(new DateTimeImmutable($end));

        return $request;
    }
}
