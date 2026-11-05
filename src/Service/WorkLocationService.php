<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WorkLocation;
use App\Entity\WorkLocationType;
use App\Repository\WorkLocationRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sets or clears the work location of a user's day (shared by UI and API).
 */
final readonly class WorkLocationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private WorkLocationRepository $locationRepo,
        private TimeEditPolicy $policy,
    ) {}

    /**
     * @throws DomainRuleViolation on edit locks or an unknown/inactive location type
     */
    public function setForDay(User $user, DateTimeImmutable $day, ?WorkLocationType $type, bool $enforceLocks = true): WorkLocation
    {
        $day = $day->setTime(0, 0);

        if ($enforceLocks) {
            $this->policy->assertCanEdit($day);
        }

        if (!$type || !$type->isActive()) {
            throw new DomainRuleViolation('work_location_invalid', 'flash.work_location_invalid', propertyPath: 'locationTypeId');
        }

        $location = $this->locationRepo->findForUserOnDate($user, $day);
        if ($location === null) {
            $location = new WorkLocation();
            $location->setUser($user);
            $location->setDate($day);
            $this->em->persist($location);
        }

        $location->setLocationType($type);
        $this->em->flush();

        return $location;
    }

    /**
     * Removes an explicit work location; the day falls back to the default location type again.
     *
     * @return bool whether a record existed
     * @throws DomainRuleViolation on edit locks
     */
    public function clearForDay(User $user, DateTimeImmutable $day, bool $enforceLocks = true): bool
    {
        $day = $day->setTime(0, 0);

        if ($enforceLocks) {
            $this->policy->assertCanEdit($day);
        }

        $location = $this->locationRepo->findForUserOnDate($user, $day);
        if ($location === null) {
            return false;
        }

        $this->em->remove($location);
        $this->em->flush();

        return true;
    }
}
