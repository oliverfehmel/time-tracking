<?php

namespace App\Service;

use App\Entity\TimeEntry;
use App\Entity\User;
use App\Repository\TimeEntryRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Timer and time-entry writes shared by the UI and the API.
 *
 * `$enforceLocks` applies the edit locks of {@see TimeEditPolicy}. The UI always enforces them; the API skips them for
 * admins (see ADR 0001).
 */
final readonly class TimeEntryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TimeEntryRepository $repo,
        private TimeEntryOverlapResolver $overlapResolver,
        private TimeEditPolicy $policy,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws DomainRuleViolation if a timer is already running
     */
    public function start(User $user): TimeEntry
    {
        if ($this->repo->findRunningForUser($user)) {
            throw new DomainRuleViolation('timer_already_running', 'flash.timer_already_running', status: DomainRuleViolation::CONFLICT);
        }

        $entry = new TimeEntry();
        $entry->setUser($user);
        $entry->setStartedAt($this->now());

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /**
     * @throws DomainRuleViolation if no timer is running
     */
    public function stop(User $user): TimeEntry
    {
        $running = $this->repo->findRunningForUser($user);
        if (!$running) {
            throw new DomainRuleViolation('no_running_timer', 'flash.no_running_timer', status: DomainRuleViolation::CONFLICT);
        }

        $running->setStoppedAt($this->now());
        $this->em->flush();

        return $running;
    }

    /**
     * Persists a new entry. Overlaps with existing entries are cut away.
     *
     * @return bool whether the time range was adjusted to avoid an overlap
     * @throws DomainRuleViolation on edit locks or when the range is completely covered by existing entries
     */
    public function create(TimeEntry $entry, bool $enforceLocks = true): bool
    {
        if ($enforceLocks) {
            $this->policy->assertCanEdit($entry->getStartedAt()->setTime(0, 0));
        }

        $adjusted = $this->resolveOverlaps($entry);

        $this->em->persist($entry);
        $this->em->flush();

        return $adjusted;
    }

    /**
     * Saves changes to an already managed entry.
     *
     * @param DateTimeImmutable $originalStart start before the change — its month must be editable, too
     * @return bool whether the time range was adjusted to avoid an overlap
     * @throws DomainRuleViolation on edit locks or when the range is completely covered by other entries
     */
    public function update(TimeEntry $entry, DateTimeImmutable $originalStart, bool $enforceLocks = true): bool
    {
        try {
            if ($enforceLocks) {
                $this->policy->assertCanEdit($originalStart->setTime(0, 0), $entry->getStartedAt()->setTime(0, 0));
            }

            $adjusted = $this->resolveOverlaps($entry);
        } catch (DomainRuleViolation $violation) {
            // Do not leave the rejected change in the unit of work, a later flush would persist it.
            $this->em->refresh($entry);
            throw $violation;
        }

        $this->em->flush();

        return $adjusted;
    }

    /**
     * @throws DomainRuleViolation on edit locks
     */
    public function delete(TimeEntry $entry, bool $enforceLocks = true): void
    {
        if ($enforceLocks) {
            $this->policy->assertCanEdit($entry->getStartedAt()->setTime(0, 0));
        }

        $this->em->remove($entry);
        $this->em->flush();
    }

    private function resolveOverlaps(TimeEntry $entry): bool
    {
        $start = $entry->getStartedAt();
        $end   = $entry->getStoppedAt();

        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
            return false;
        }

        $existing = $this->repo->findOverlappingForUser($entry->getUser(), $start, $end, $entry->getId() !== null ? $entry : null);
        if ($existing === []) {
            return false;
        }

        if (!$this->overlapResolver->resolve($entry, $existing, $this->now())) {
            throw new DomainRuleViolation('time_range_fully_overlaps', 'flash.time_range_fully_overlaps', propertyPath: 'startedAt');
        }

        return $entry->getStartedAt() != $start || $entry->getStoppedAt() != $end;
    }

    private function now(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($this->clock->now());
    }
}
