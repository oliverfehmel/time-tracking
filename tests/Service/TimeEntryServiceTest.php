<?php

namespace App\Tests\Service;

use App\Entity\TimeEntry;
use App\Entity\User;
use App\Service\DomainRuleViolation;
use App\Service\TimeEntryService;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;

final class TimeEntryServiceTest extends DatabaseTestCase
{
    private TimeEntryService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-11-05 10:00:00');
        // The settings row always exists in a running app; getOrCreate() would otherwise flush mid-test.
        $this->factory->settings();
        $this->service = $this->service(TimeEntryService::class);
        $this->user    = $this->factory->user();
    }

    public function testStartUsesClockAndStopFinishesEntry(): void
    {
        $entry = $this->service->start($this->user);
        self::assertSame('2026-11-05 10:00:00', $entry->getStartedAt()->format('Y-m-d H:i:s'));
        self::assertTrue($entry->isRunning());

        self::mockTime('2026-11-05 12:30:00');
        $stopped = $this->service->stop($this->user);

        self::assertSame($entry->getId(), $stopped->getId());
        self::assertSame('2026-11-05 12:30:00', $stopped->getStoppedAt()->format('Y-m-d H:i:s'));
    }

    public function testStartWhileRunningIsAConflict(): void
    {
        $this->service->start($this->user);

        $violation = $this->catchViolation(fn() => $this->service->start($this->user));

        self::assertSame('timer_already_running', $violation->rule);
        self::assertSame(409, $violation->status);
    }

    public function testStopWithoutTimerIsAConflict(): void
    {
        $violation = $this->catchViolation(fn() => $this->service->stop($this->user));

        self::assertSame('no_running_timer', $violation->rule);
        self::assertSame(409, $violation->status);
    }

    public function testCreateWithoutOverlap(): void
    {
        $entry = $this->entry('2026-11-04 08:00', '2026-11-04 12:00');

        self::assertFalse($this->service->create($entry));
        self::assertNotNull($entry->getId());
    }

    public function testCreateCutsAwayPartialOverlap(): void
    {
        $this->factory->timeEntry($this->user, '2026-11-04 08:00', '2026-11-04 10:00');
        $entry = $this->entry('2026-11-04 09:00', '2026-11-04 12:00');

        self::assertTrue($this->service->create($entry));
        self::assertSame('10:00', $entry->getStartedAt()->format('H:i'));
        self::assertSame('12:00', $entry->getStoppedAt()->format('H:i'));
    }

    public function testCreateRejectsFullOverlap(): void
    {
        $this->factory->timeEntry($this->user, '2026-11-04 08:00', '2026-11-04 13:00');

        $violation = $this->catchViolation(fn() => $this->service->create($this->entry('2026-11-04 09:00', '2026-11-04 12:00')));

        self::assertSame('time_range_fully_overlaps', $violation->rule);
        self::assertSame(422, $violation->status);
        self::assertCount(1, $this->em->getRepository(TimeEntry::class)->findBy(['user' => $this->user]));
    }

    public function testRunningTimerCountsAsOccupiedUntilNow(): void
    {
        $this->factory->timeEntry($this->user, '2026-11-05 08:00', null);

        $violation = $this->catchViolation(fn() => $this->service->create($this->entry('2026-11-05 08:30', '2026-11-05 09:30')));

        self::assertSame('time_range_fully_overlaps', $violation->rule);
    }

    public function testLockedMonthIsEnforcedUnlessDisabled(): void
    {
        $violation = $this->catchViolation(fn() => $this->service->create($this->entry('2026-09-30 08:00', '2026-09-30 12:00')));
        self::assertSame('month_not_editable', $violation->rule);
        self::assertSame(403, $violation->status);

        // Previous month is still editable
        self::assertFalse($this->service->create($this->entry('2026-10-01 08:00', '2026-10-01 12:00')));
        // Admin path via API skips the locks
        self::assertFalse($this->service->create($this->entry('2026-09-30 08:00', '2026-09-30 12:00'), enforceLocks: false));
    }

    public function testEditingSettingIsEnforced(): void
    {
        $this->factory->settings(['usersAreAllowedToChangeTimeEntries' => false]);

        $violation = $this->catchViolation(fn() => $this->service->create($this->entry('2026-11-04 08:00', '2026-11-04 12:00')));

        self::assertSame('time_editing_disabled', $violation->rule);
        self::assertSame(403, $violation->status);
    }

    public function testUpdateChecksOriginalAndNewMonth(): void
    {
        $entry = $this->factory->timeEntry($this->user, '2026-09-15 08:00', '2026-09-15 12:00');
        $original = $entry->getStartedAt();
        $entry->setStartedAt(new DateTimeImmutable('2026-11-04 08:00'));
        $entry->setStoppedAt(new DateTimeImmutable('2026-11-04 12:00'));

        $violation = $this->catchViolation(fn() => $this->service->update($entry, $original));

        self::assertSame('month_not_editable', $violation->rule);
        // The rejected change was reverted in the unit of work
        self::assertSame('2026-09-15 08:00', $entry->getStartedAt()->format('Y-m-d H:i'));
    }

    public function testUpdateIgnoresItselfWhenCheckingOverlaps(): void
    {
        $entry    = $this->factory->timeEntry($this->user, '2026-11-04 08:00', '2026-11-04 12:00');
        $original = $entry->getStartedAt();
        $entry->setStoppedAt(new DateTimeImmutable('2026-11-04 13:00'));

        self::assertFalse($this->service->update($entry, $original));
        $this->em->clear();
        self::assertSame('13:00', $this->em->find(TimeEntry::class, $entry->getId())->getStoppedAt()->format('H:i'));
    }

    public function testDeleteRespectsLocks(): void
    {
        $old = $this->factory->timeEntry($this->user, '2026-08-03 08:00', '2026-08-03 12:00');
        $this->catchViolation(fn() => $this->service->delete($old));

        $this->service->delete($old, enforceLocks: false);
        self::assertNull($old->getId() ? $this->em->find(TimeEntry::class, $old->getId()) : null);
    }

    private function entry(string $start, string $stop): TimeEntry
    {
        $entry = new TimeEntry();
        $entry->setUser($this->user);
        $entry->setStartedAt(new DateTimeImmutable($start));
        $entry->setStoppedAt(new DateTimeImmutable($stop));

        return $entry;
    }

    private function catchViolation(callable $fn): DomainRuleViolation
    {
        try {
            $fn();
        } catch (DomainRuleViolation $violation) {
            return $violation;
        }

        self::fail('Expected a DomainRuleViolation');
    }
}
