<?php

namespace App\Tests\Service;

use App\Entity\Settings;
use App\Entity\TimeEntry;
use App\Service\TimeTrackingCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TimeTrackingCalculatorTest extends TestCase
{
    private TimeTrackingCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new TimeTrackingCalculator();
    }

    // --- workedSecondsInRange ---

    public function testWorkedSecondsInRange_singleFullEntry(): void
    {
        $from  = new DateTimeImmutable('2024-01-15 08:00:00');
        $to    = new DateTimeImmutable('2024-01-15 17:00:00');
        $now   = new DateTimeImmutable('2024-01-15 18:00:00');
        $entry = $this->makeEntry('2024-01-15 08:00:00', '2024-01-15 17:00:00');

        $this->assertSame(9 * 3600, $this->calc->workedSecondsInRange([$entry], $from, $to, $now));
    }

    public function testWorkedSecondsInRange_entryClippedAtRangeStart(): void
    {
        $from  = new DateTimeImmutable('2024-01-15 09:00:00');
        $to    = new DateTimeImmutable('2024-01-15 17:00:00');
        $now   = new DateTimeImmutable('2024-01-15 18:00:00');
        // Entry starts one hour before range
        $entry = $this->makeEntry('2024-01-15 08:00:00', '2024-01-15 17:00:00');

        $this->assertSame(8 * 3600, $this->calc->workedSecondsInRange([$entry], $from, $to, $now));
    }

    public function testWorkedSecondsInRange_entryClippedAtRangeEnd(): void
    {
        $from  = new DateTimeImmutable('2024-01-15 08:00:00');
        $to    = new DateTimeImmutable('2024-01-15 16:00:00');
        $now   = new DateTimeImmutable('2024-01-15 18:00:00');
        // Entry ends one hour after range
        $entry = $this->makeEntry('2024-01-15 08:00:00', '2024-01-15 17:00:00');

        $this->assertSame(8 * 3600, $this->calc->workedSecondsInRange([$entry], $from, $to, $now));
    }

    public function testWorkedSecondsInRange_runningEntry_usesNowAsEnd(): void
    {
        $from  = new DateTimeImmutable('2024-01-15 08:00:00');
        $to    = new DateTimeImmutable('2024-01-15 17:00:00');
        $now   = new DateTimeImmutable('2024-01-15 10:00:00'); // only 2h elapsed
        $entry = $this->makeEntry('2024-01-15 08:00:00', null); // running

        $this->assertSame(2 * 3600, $this->calc->workedSecondsInRange([$entry], $from, $to, $now));
    }

    public function testWorkedSecondsInRange_entryCompletelyOutsideRange_ignored(): void
    {
        $from  = new DateTimeImmutable('2024-01-15 08:00:00');
        $to    = new DateTimeImmutable('2024-01-15 17:00:00');
        $now   = new DateTimeImmutable('2024-01-15 18:00:00');
        $entry = $this->makeEntry('2024-01-15 17:00:00', '2024-01-15 18:00:00');

        $this->assertSame(0, $this->calc->workedSecondsInRange([$entry], $from, $to, $now));
    }

    public function testWorkedSecondsInRange_multipleEntries_summed(): void
    {
        $from   = new DateTimeImmutable('2024-01-15 00:00:00');
        $to     = new DateTimeImmutable('2024-01-16 00:00:00');
        $now    = new DateTimeImmutable('2024-01-15 18:00:00');
        $entry1 = $this->makeEntry('2024-01-15 08:00:00', '2024-01-15 12:00:00'); // 4h
        $entry2 = $this->makeEntry('2024-01-15 13:00:00', '2024-01-15 17:00:00'); // 4h

        $this->assertSame(8 * 3600, $this->calc->workedSecondsInRange([$entry1, $entry2], $from, $to, $now));
    }

    public function testWorkedSecondsInRange_noEntries_returnsZero(): void
    {
        $from = new DateTimeImmutable('2024-01-15 08:00:00');
        $to   = new DateTimeImmutable('2024-01-15 17:00:00');
        $now  = new DateTimeImmutable('2024-01-15 18:00:00');

        $this->assertSame(0, $this->calc->workedSecondsInRange([], $from, $to, $now));
    }

    // --- applyDailyBreakRuleSeconds ---

    public function testBreakRule_under6h_noDeduction(): void
    {
        $settings = $this->makeSettings(30, 45);
        $worked   = 5 * 3600 + 59 * 60; // 5h59m

        $this->assertSame($worked, $this->calc->applyDailyBreakRuleSeconds($worked, $settings));
    }

    public function testBreakRule_exactly6h_noDeduction(): void
    {
        // Threshold is OVER 6h, not >= 6h
        $settings = $this->makeSettings(30, 45);

        $this->assertSame(6 * 3600, $this->calc->applyDailyBreakRuleSeconds(6 * 3600, $settings));
    }

    public function testBreakRule_over6h_deducts30min(): void
    {
        $settings = $this->makeSettings(30, 45);
        $worked   = 7 * 3600; // 7h

        $this->assertSame(7 * 3600 - 30 * 60, $this->calc->applyDailyBreakRuleSeconds($worked, $settings));
    }

    public function testBreakRule_over9h_deducts45min(): void
    {
        $settings = $this->makeSettings(30, 45);
        $worked   = 10 * 3600; // 10h

        $this->assertSame(10 * 3600 - 45 * 60, $this->calc->applyDailyBreakRuleSeconds($worked, $settings));
    }

    public function testBreakRule_configuredZeroPause_noDeduction(): void
    {
        $settings = $this->makeSettings(0, 0);

        $this->assertSame(10 * 3600, $this->calc->applyDailyBreakRuleSeconds(10 * 3600, $settings));
    }

    public function testBreakRule_neverGoesNegative(): void
    {
        // Break configured larger than worked time — result must clamp to 0
        $settings = $this->makeSettings(30, 600); // 600 min pause after 9h
        $worked   = 9 * 3600 + 1; // just over 9h threshold

        $this->assertSame(0, $this->calc->applyDailyBreakRuleSeconds($worked, $settings));
    }

    // --- formatSeconds ---

    public function testFormatSeconds_fullHours(): void
    {
        $this->assertSame('8:00', $this->calc->formatSeconds(8 * 3600));
    }

    public function testFormatSeconds_hoursAndMinutes(): void
    {
        $this->assertSame('7:30', $this->calc->formatSeconds(7 * 3600 + 30 * 60));
    }

    public function testFormatSeconds_zero(): void
    {
        $this->assertSame('0:00', $this->calc->formatSeconds(0));
    }

    public function testFormatSeconds_minutesPadded(): void
    {
        $this->assertSame('1:05', $this->calc->formatSeconds(3600 + 5 * 60));
    }

    public function testFormatSeconds_largeValue(): void
    {
        $this->assertSame('100:00', $this->calc->formatSeconds(100 * 3600));
    }

    // --- formatSignedSeconds ---

    public function testFormatSignedSeconds_positive(): void
    {
        $this->assertSame('+1:30', $this->calc->formatSignedSeconds(90 * 60));
    }

    public function testFormatSignedSeconds_negative(): void
    {
        $this->assertSame('-2:00', $this->calc->formatSignedSeconds(-2 * 3600));
    }

    public function testFormatSignedSeconds_zero(): void
    {
        $this->assertSame('+0:00', $this->calc->formatSignedSeconds(0));
    }

    // --- isEditableMonth ---

    public function testIsEditableMonth_currentMonth_returnsTrue(): void
    {
        $now  = new DateTimeImmutable('2024-06-15');
        $date = new DateTimeImmutable('2024-06-01');

        $this->assertTrue($this->calc->isEditableMonth($date, $now));
    }

    public function testIsEditableMonth_previousMonth_returnsTrue(): void
    {
        $now  = new DateTimeImmutable('2024-06-15');
        $date = new DateTimeImmutable('2024-05-20');

        $this->assertTrue($this->calc->isEditableMonth($date, $now));
    }

    public function testIsEditableMonth_twoMonthsAgo_returnsFalse(): void
    {
        $now  = new DateTimeImmutable('2024-06-15');
        $date = new DateTimeImmutable('2024-04-30');

        $this->assertFalse($this->calc->isEditableMonth($date, $now));
    }

    public function testIsEditableMonth_futureMonth_returnsFalse(): void
    {
        $now  = new DateTimeImmutable('2024-06-15');
        $date = new DateTimeImmutable('2024-07-01');

        $this->assertFalse($this->calc->isEditableMonth($date, $now));
    }

    public function testIsEditableMonth_januaryPreviousMonth_isDecemberPreviousYear(): void
    {
        $now  = new DateTimeImmutable('2024-01-15');
        $date = new DateTimeImmutable('2023-12-31');

        $this->assertTrue($this->calc->isEditableMonth($date, $now));
    }

    // --- sumNetSecondsPerDay ---

    public function testSumNetSecondsPerDay_twoConsecutiveDays(): void
    {
        $settings = $this->makeSettings(0, 0); // no break deduction
        $from     = new DateTimeImmutable('2024-01-15 00:00:00');
        $to       = new DateTimeImmutable('2024-01-17 00:00:00');
        $now      = new DateTimeImmutable('2024-01-17 12:00:00');

        $entries = [
            $this->makeEntry('2024-01-15 08:00:00', '2024-01-15 16:00:00'), // 8h
            $this->makeEntry('2024-01-16 08:00:00', '2024-01-16 16:00:00'), // 8h
        ];

        $this->assertSame(16 * 3600, $this->calc->sumNetSecondsPerDay($entries, $from, $to, $now, $settings));
    }

    public function testSumNetSecondsPerDay_breakDeductedPerDay(): void
    {
        $settings = $this->makeSettings(30, 45); // 30min after 6h
        $from     = new DateTimeImmutable('2024-01-15 00:00:00');
        $to       = new DateTimeImmutable('2024-01-17 00:00:00');
        $now      = new DateTimeImmutable('2024-01-17 12:00:00');

        $entries = [
            $this->makeEntry('2024-01-15 08:00:00', '2024-01-15 16:00:00'), // 8h → -30min
            $this->makeEntry('2024-01-16 08:00:00', '2024-01-16 16:00:00'), // 8h → -30min
        ];

        $expected = 2 * (8 * 3600 - 30 * 60);
        $this->assertSame($expected, $this->calc->sumNetSecondsPerDay($entries, $from, $to, $now, $settings));
    }

    // --- Helpers ---

    private function makeEntry(string $start, ?string $end): TimeEntry
    {
        $entry = new TimeEntry();
        $entry->setStartedAt(new DateTimeImmutable($start));
        if ($end !== null) {
            $entry->setStoppedAt(new DateTimeImmutable($end));
        }
        return $entry;
    }

    private function makeSettings(int $pauseSixMinutes, int $pauseNineMinutes): Settings
    {
        $s = new Settings();
        $s->setAutoPauseAfterSixHours($pauseSixMinutes);
        $s->setAutoPauseAfterNineHours($pauseNineMinutes);
        return $s;
    }
}
