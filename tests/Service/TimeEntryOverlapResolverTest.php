<?php

namespace App\Tests\Service;

use App\Entity\TimeEntry;
use App\Service\TimeEntryOverlapResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TimeEntryOverlapResolverTest extends TestCase
{
    public function testResolveKeepsEntryWhenThereIsNoOverlap(): void
    {
        $entry = $this->makeEntry('2024-01-15 09:00', '2024-01-15 10:00');
        $existing = [$this->makeEntry('2024-01-15 10:00', '2024-01-15 11:00')];

        $resolved = (new TimeEntryOverlapResolver())->resolve($entry, $existing, new DateTimeImmutable('2024-01-15 12:00'));

        $this->assertTrue($resolved);
        $this->assertSame('09:00', $entry->getStartedAt()->format('H:i'));
        $this->assertSame('10:00', $entry->getStoppedAt()->format('H:i'));
    }

    public function testResolveTrimsOverlappingStart(): void
    {
        $entry = $this->makeEntry('2024-01-15 09:00', '2024-01-15 12:00');
        $existing = [$this->makeEntry('2024-01-15 09:00', '2024-01-15 10:00')];

        $resolved = (new TimeEntryOverlapResolver())->resolve($entry, $existing, new DateTimeImmutable('2024-01-15 12:00'));

        $this->assertTrue($resolved);
        $this->assertSame('10:00', $entry->getStartedAt()->format('H:i'));
        $this->assertSame('12:00', $entry->getStoppedAt()->format('H:i'));
    }

    public function testResolveReturnsFalseWhenEntryIsFullyCovered(): void
    {
        $entry = $this->makeEntry('2024-01-15 09:00', '2024-01-15 10:00');
        $existing = [$this->makeEntry('2024-01-15 08:00', '2024-01-15 11:00')];

        $resolved = (new TimeEntryOverlapResolver())->resolve($entry, $existing, new DateTimeImmutable('2024-01-15 12:00'));

        $this->assertFalse($resolved);
    }

    private function makeEntry(string $start, string $end): TimeEntry
    {
        $entry = new TimeEntry();
        $entry->setStartedAt(new DateTimeImmutable($start));
        $entry->setStoppedAt(new DateTimeImmutable($end));

        return $entry;
    }
}
