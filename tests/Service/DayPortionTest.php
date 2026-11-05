<?php

namespace App\Tests\Service;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\Holiday;
use App\Entity\User;
use App\Service\DayPortion;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DayPortionTest extends TestCase
{
    // --- holidayMap ---

    public function testHolidayMap_fullAndHalfHolidays(): void
    {
        $map = DayPortion::holidayMap([
            $this->makeHoliday('2024-12-24', halfDay: true),
            $this->makeHoliday('2024-12-25'),
        ]);

        $this->assertSame(['2024-12-24' => 0.5, '2024-12-25' => 1.0], $map);
    }

    public function testHolidayMap_twoHolidaysSameDay_maximumWins(): void
    {
        $map = DayPortion::holidayMap([
            $this->makeHoliday('2024-12-24', halfDay: true),
            $this->makeHoliday('2024-12-24'),
            $this->makeHoliday('2024-12-24', halfDay: true),
        ]);

        $this->assertSame(['2024-12-24' => 1.0], $map);
    }

    // --- addAbsence ---

    public function testAddAbsence_fullDays_clampedToRange(): void
    {
        $map = [];
        DayPortion::addAbsence(
            $map,
            $this->makeAbsence('2024-01-30', '2024-02-02'),
            new DateTimeImmutable('2024-02-01'),
            new DateTimeImmutable('2024-03-01'),
        );

        $this->assertSame(['2024-02-01' => 1.0, '2024-02-02' => 1.0], $map);
    }

    public function testAddAbsence_twoHalfDaysSameDay_sumToFullDay(): void
    {
        $map  = [];
        $from = new DateTimeImmutable('2024-01-01');
        $to   = new DateTimeImmutable('2024-02-01');

        DayPortion::addAbsence($map, $this->makeAbsence('2024-01-15', '2024-01-15', halfDay: true), $from, $to);
        $this->assertSame(['2024-01-15' => 0.5], $map);

        DayPortion::addAbsence($map, $this->makeAbsence('2024-01-15', '2024-01-15', halfDay: true), $from, $to);
        $this->assertSame(['2024-01-15' => 1.0], $map);
    }

    public function testAddAbsence_sumIsCappedAtFullDay(): void
    {
        $map  = ['2024-01-15' => 1.0];
        DayPortion::addAbsence(
            $map,
            $this->makeAbsence('2024-01-15', '2024-01-15', halfDay: true),
            new DateTimeImmutable('2024-01-01'),
            new DateTimeImmutable('2024-02-01'),
        );

        $this->assertSame(['2024-01-15' => 1.0], $map);
    }

    // --- workFraction / chargeableFraction ---

    #[DataProvider('fractionProvider')]
    public function testFractions(float $holiday, float $absence, float $expectedWork, float $expectedChargeable): void
    {
        $this->assertSame($expectedWork, DayPortion::workFraction($holiday, $absence));
        $this->assertSame($expectedChargeable, DayPortion::chargeableFraction($holiday, $absence));
    }

    public static function fractionProvider(): iterable
    {
        //                                       holiday, absence, work, chargeable
        yield 'normal workday'                => [0.0, 0.0, 1.0, 0.0];
        yield 'full absence'                  => [0.0, 1.0, 0.0, 1.0];
        yield 'half absence'                  => [0.0, 0.5, 0.5, 0.5];
        yield 'full holiday'                  => [1.0, 0.0, 0.0, 0.0];
        yield 'half holiday'                  => [0.5, 0.0, 0.5, 0.0];
        yield 'full absence on half holiday'  => [0.5, 1.0, 0.0, 0.5];
        yield 'half absence on half holiday'  => [0.5, 0.5, 0.0, 0.5];
        yield 'full absence on full holiday'  => [1.0, 1.0, 0.0, 0.0];
        yield 'half absence on full holiday'  => [1.0, 0.5, 0.0, 0.0];
    }

    // --- fitsAlongside ---

    public function testFitsAlongside_noOverlap_alwaysFits(): void
    {
        $this->assertTrue(DayPortion::fitsAlongside(DayPortion::FULL, []));
        $this->assertTrue(DayPortion::fitsAlongside(DayPortion::HALF, []));
    }

    public function testFitsAlongside_halfNextToHalf_fits(): void
    {
        $existing = [$this->makeAbsence('2024-01-15', '2024-01-15', halfDay: true)];

        $this->assertTrue(DayPortion::fitsAlongside(DayPortion::HALF, $existing));
    }

    public function testFitsAlongside_fullNextToHalf_doesNotFit(): void
    {
        $existing = [$this->makeAbsence('2024-01-15', '2024-01-15', halfDay: true)];

        $this->assertFalse(DayPortion::fitsAlongside(DayPortion::FULL, $existing));
    }

    public function testFitsAlongside_halfNextToFull_doesNotFit(): void
    {
        $existing = [$this->makeAbsence('2024-01-15', '2024-01-15')];

        $this->assertFalse(DayPortion::fitsAlongside(DayPortion::HALF, $existing));
    }

    public function testFitsAlongside_thirdHalfDay_doesNotFit(): void
    {
        $existing = [
            $this->makeAbsence('2024-01-15', '2024-01-15', halfDay: true),
            $this->makeAbsence('2024-01-15', '2024-01-15', halfDay: true),
        ];

        $this->assertFalse(DayPortion::fitsAlongside(DayPortion::HALF, $existing));
    }

    // --- Helpers ---

    private function makeHoliday(string $date, bool $halfDay = false): Holiday
    {
        return (new Holiday())
            ->setName('Feiertag')
            ->setDate(new DateTimeImmutable($date))
            ->setHalfDay($halfDay);
    }

    private function makeAbsence(string $start, string $end, bool $halfDay = false): AbsenceRequest
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
}
