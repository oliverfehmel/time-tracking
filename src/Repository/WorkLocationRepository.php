<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WorkLocation;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WorkLocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkLocation::class);
    }

    public function findForUserOnDate(User $user, DateTimeImmutable $date): ?WorkLocation
    {
        return $this->findOneBy([
            'user' => $user,
            'date' => $date->setTime(0, 0, 0),
        ]);
    }

    /** Returns ['Y-m-d' => WorkLocationType] map for the given year range. */
    public function buildTypeMapForUser(User $user, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $results = $this->createQueryBuilder('wl')
            ->addSelect('wlt')
            ->join('wl.locationType', 'wlt')
            ->andWhere('wl.user = :user')
            ->andWhere('wl.date >= :from')
            ->andWhere('wl.date < :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($results as $wl) {
            $map[$wl->getDate()->format('Y-m-d')] = $wl->getLocationType();
        }
        return $map;
    }
}
