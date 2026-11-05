<?php

namespace App\Tests\Service;

use App\Entity\WorkLocation;
use App\Service\DomainRuleViolation;
use App\Service\WorkLocationService;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;

final class WorkLocationServiceTest extends DatabaseTestCase
{
    private WorkLocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-11-05 10:00:00');
        $this->factory->settings();
        $this->service = $this->service(WorkLocationService::class);
    }

    public function testSetCreatesAndUpdatesRecord(): void
    {
        $user = $this->factory->user();
        $home = $this->factory->workLocationType();
        $trip = $this->factory->workLocationType();

        $first  = $this->service->setForDay($user, new DateTimeImmutable('2026-11-04 15:00'), $home);
        $second = $this->service->setForDay($user, new DateTimeImmutable('2026-11-04'), $trip);

        self::assertSame($first->getId(), $second->getId());
        self::assertSame('2026-11-04 00:00', $second->getDate()->format('Y-m-d H:i'));
        self::assertSame($trip->getId(), $second->getLocationType()->getId());
    }

    public function testInactiveOrMissingTypeIsRejected(): void
    {
        $user = $this->factory->user();

        foreach ([$this->factory->workLocationType(active: false), null] as $type) {
            try {
                $this->service->setForDay($user, new DateTimeImmutable('2026-11-04'), $type);
                self::fail('Expected a violation');
            } catch (DomainRuleViolation $violation) {
                self::assertSame('work_location_invalid', $violation->rule);
            }
        }
    }

    public function testLocksApplyUnlessDisabled(): void
    {
        $user = $this->factory->user();
        $type = $this->factory->workLocationType();

        try {
            $this->service->setForDay($user, new DateTimeImmutable('2026-08-03'), $type);
            self::fail('Expected a violation');
        } catch (DomainRuleViolation $violation) {
            self::assertSame('month_not_editable', $violation->rule);
        }

        self::assertNotNull($this->service->setForDay($user, new DateTimeImmutable('2026-08-03'), $type, enforceLocks: false)->getId());
    }

    public function testClearRemovesRecord(): void
    {
        $user = $this->factory->user();
        $this->factory->workLocation($user, '2026-11-04', $this->factory->workLocationType());

        self::assertTrue($this->service->clearForDay($user, new DateTimeImmutable('2026-11-04')));
        self::assertFalse($this->service->clearForDay($user, new DateTimeImmutable('2026-11-04')));
        self::assertNull($this->em->getRepository(WorkLocation::class)->findOneBy(['user' => $user]));
    }
}
