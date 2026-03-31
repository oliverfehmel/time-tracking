<?php

namespace App\Service;

use App\Entity\Settings;
use App\Entity\TimeEntry;
use DateTimeImmutable;

final class TimeTrackingCalculator
{
    /**
     * @param TimeEntry[] $entries
     */
    public function workedSecondsInRange(array $entries, DateTimeImmutable $from, DateTimeImmutable $to, DateTimeImmutable $now): int
    {
        $sum = 0;

        foreach ($entries as $e) {
            $start = $e->getStartedAt();
            $end = $e->getStoppedAt() ?? $now;

            // Clip auf Range
            if ($end <= $from || $start >= $to) {
                continue;
            }
            $clipStart = $start < $from ? $from : $start;
            $clipEnd = $end > $to ? $to : $end;

            $sum += max(0, $clipEnd->getTimestamp() - $clipStart->getTimestamp());
        }

        return $sum;
    }

    /**
     * Pausenabzug pro Tag auf Sekundenbasis
     * >6h => -30 min, >9h => -45 min
     */
    public function applyDailyBreakRuleSeconds(int $workedSeconds, Settings $settings): int
    {
        $pauseNineHours = $settings->getAutoPauseAfterNineHours();
        $pauseSixHours = $settings->getAutoPauseAfterSixHours();
        if ($workedSeconds > 9 * 3600) {
            return max(0, $workedSeconds - $pauseNineHours * 60);
        }
        if ($workedSeconds > 6 * 3600) {
            return max(0, $workedSeconds - $pauseSixHours * 60);
        }
        return $workedSeconds;
    }

    public function formatSeconds(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return sprintf('%d:%02d', $h, $m);
    }

    public function formatSignedSeconds(int $seconds): string
    {
        $sign = $seconds < 0 ? '-' : '+';
        $abs = abs($seconds);

        return $sign . $this->formatSeconds($abs);
    }

    public function sumNetSecondsPerDay(
        array $entries,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        DateTimeImmutable $now,
        Settings $settings,
    ): int {
        $sum = 0;
        for ($day = $from; $day < $to; $day = $day->modify('+1 day')) {
            $dayStart = $day->setTime(0, 0);
            $dayEnd   = $dayStart->modify('+1 day');
            $raw      = $this->workedSecondsInRange($entries, $dayStart, $dayEnd, $now);
            $sum     += $this->applyDailyBreakRuleSeconds($raw, $settings);
        }
        return $sum;
    }

    public function isEditableMonth(DateTimeImmutable $date, DateTimeImmutable $now): bool
    {
        $month   = $date->format('Y-m');
        $current = $now->format('Y-m');
        $prev    = $now->modify('first day of this month')->modify('-1 month')->format('Y-m');

        return $month === $current || $month === $prev;
    }
}
