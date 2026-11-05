<?php

namespace App\Tests\Service;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\Holiday;
use App\Entity\User;
use App\Repository\AbsenceRequestRepository;
use App\Repository\HolidayRepository;
use App\Service\AbsenceRequestValidator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AbsenceRequestValidatorTest extends TestCase
{
    private const YEAR = 2026;

    // --- Datumslogik ---

    public function testValidRequest_noOverlap_returnsNull(): void
    {
        $this->assertNull($this->validate($this->makeRequest('2026-07-06', '2026-07-10')));
    }

    public function testEndBeforeStart(): void
    {
        $this->assertSame('absence.request.end_before_start', $this->validate($this->makeRequest('2026-07-10', '2026-07-06')));
    }

    public function testWrongYear(): void
    {
        $this->assertSame('absence.request.wrong_year', $this->validate($this->makeRequest('2025-07-06', '2025-07-10')));
    }

    public function testRangeAcrossYearBoundary_isAllowed(): void
    {
        $this->assertNull($this->validate($this->makeRequest('2025-12-29', '2026-01-02')));
    }

    // --- halbe Tage: nur Einzeltag ---

    public function testHalfDay_singleDay_isAllowed(): void
    {
        $this->assertNull($this->validate($this->makeRequest('2026-07-06', '2026-07-06', halfDay: true)));
    }

    public function testHalfDay_multipleDays_isRejected(): void
    {
        $this->assertSame(
            'absence.request.half_day_single_day',
            $this->validate($this->makeRequest('2026-07-06', '2026-07-07', halfDay: true)),
        );
    }

    // --- Überschneidungen ---

    public function testHalfNextToHalf_isAllowed(): void
    {
        $existing = [$this->makeRequest('2026-07-06', '2026-07-06', halfDay: true)];

        $this->assertNull($this->validate($this->makeRequest('2026-07-06', '2026-07-06', halfDay: true), $existing));
    }

    public function testFullNextToHalf_isRejected(): void
    {
        $existing = [$this->makeRequest('2026-07-06', '2026-07-06', halfDay: true)];

        $this->assertSame(
            'absence.request.overlaps_existing',
            $this->validate($this->makeRequest('2026-07-06', '2026-07-08'), $existing),
        );
    }

    public function testHalfNextToFull_isRejected(): void
    {
        $existing = [$this->makeRequest('2026-07-06', '2026-07-08')];

        $this->assertSame(
            'absence.request.overlaps_existing',
            $this->validate($this->makeRequest('2026-07-07', '2026-07-07', halfDay: true), $existing),
        );
    }

    public function testFullNextToFull_isRejected(): void
    {
        $existing = [$this->makeRequest('2026-07-06', '2026-07-08')];

        $this->assertSame(
            'absence.request.overlaps_existing',
            $this->validate($this->makeRequest('2026-07-08', '2026-07-10'), $existing),
        );
    }

    // --- halbe Feiertage ---

    public function testHalfNextToHalf_onHalfHoliday_isRejected(): void
    {
        $existing = [$this->makeRequest('2026-12-24', '2026-12-24', halfDay: true)];
        $holidays = [$this->makeHoliday('2026-12-24', halfDay: true)];

        $this->assertSame(
            'absence.request.overlaps_existing',
            $this->validate($this->makeRequest('2026-12-24', '2026-12-24', halfDay: true), $existing, $holidays),
        );
    }

    public function testSingleHalf_onHalfHoliday_isAllowed(): void
    {
        $holidays = [$this->makeHoliday('2026-12-24', halfDay: true)];

        $this->assertNull($this->validate($this->makeRequest('2026-12-24', '2026-12-24', halfDay: true), [], $holidays));
    }

    public function testFullRangeOverHalfHoliday_withoutOverlap_isAllowed(): void
    {
        $holidays = [$this->makeHoliday('2026-12-24', halfDay: true)];

        $this->assertNull($this->validate($this->makeRequest('2026-12-21', '2026-12-31'), [], $holidays));
    }

    // --- Helpers ---

    /**
     * @param AbsenceRequest[] $overlapping
     * @param Holiday[]        $holidays
     */
    private function validate(AbsenceRequest $request, array $overlapping = [], array $holidays = []): ?string
    {
        $requestRepo = $this->createStub(AbsenceRequestRepository::class);
        $requestRepo->method('findOverlappingApprovedOrPending')->willReturn($overlapping);

        $holidayRepo = $this->createStub(HolidayRepository::class);
        $holidayRepo->method('findForUserBetween')->willReturn($holidays);

        return (new AbsenceRequestValidator($requestRepo, $holidayRepo))->validateDates($request, self::YEAR);
    }

    private function makeRequest(string $start, string $end, bool $halfDay = false): AbsenceRequest
    {
        $type = new AbsenceType();
        $type->setName('Urlaub');
        $type->setKeyName('vacation');

        return (new AbsenceRequest())
            ->setRequestedBy(new User())
            ->setType($type)
            ->setStartDate(new DateTimeImmutable($start))
            ->setEndDate(new DateTimeImmutable($end))
            ->setHalfDay($halfDay);
    }

    private function makeHoliday(string $date, bool $halfDay = false): Holiday
    {
        return (new Holiday())
            ->setName('Feiertag')
            ->setDate(new DateTimeImmutable($date))
            ->setHalfDay($halfDay);
    }
}
