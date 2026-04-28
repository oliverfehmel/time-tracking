<?php

namespace App\Service;

use App\Entity\TimeEntry;
use DateTimeImmutable;

final class TimeEntryOverlapResolver
{
    /**
     * @param TimeEntry[] $existingEntries
     */
    public function resolve(TimeEntry $entry, array $existingEntries, DateTimeImmutable $now): bool
    {
        $start = $entry->getStartedAt();
        $end = $entry->getStoppedAt();

        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
            return true;
        }

        if ($end <= $start) {
            return false;
        }

        $segments = [[$start, $end]];

        foreach ($existingEntries as $existing) {
            if ($existing->getId() !== null && $existing->getId() === $entry->getId()) {
                continue;
            }

            $otherStart = $existing->getStartedAt();
            $otherEnd = $existing->getStoppedAt() ?? $now;
            if (!$otherStart instanceof DateTimeImmutable || $otherEnd <= $otherStart) {
                continue;
            }

            $segments = $this->subtractInterval($segments, $otherStart, $otherEnd);
            if ($segments === []) {
                return false;
            }
        }

        [$resolvedStart, $resolvedEnd] = $segments[0];
        $entry->setStartedAt($resolvedStart);
        $entry->setStoppedAt($resolvedEnd);

        return true;
    }

    /**
     * @param array<int,array{0:DateTimeImmutable,1:DateTimeImmutable}> $segments
     * @return array<int,array{0:DateTimeImmutable,1:DateTimeImmutable}>
     */
    private function subtractInterval(array $segments, DateTimeImmutable $cutStart, DateTimeImmutable $cutEnd): array
    {
        $remaining = [];

        foreach ($segments as [$start, $end]) {
            if ($cutEnd <= $start || $cutStart >= $end) {
                $remaining[] = [$start, $end];
                continue;
            }

            if ($cutStart > $start) {
                $remaining[] = [$start, min($cutStart, $end)];
            }

            if ($cutEnd < $end) {
                $remaining[] = [max($cutEnd, $start), $end];
            }
        }

        usort($remaining, fn (array $a, array $b) => $a[0] <=> $b[0]);

        return array_values(array_filter($remaining, fn (array $segment) => $segment[1] > $segment[0]));
    }
}
