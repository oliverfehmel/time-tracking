<?php

namespace App\Repository;

use App\Entity\Holiday;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HolidayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Holiday::class);
    }

    /**
     * @return Holiday[]
     */
    public function findForUserBetween(User $user, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('h')
            ->innerJoin('h.users', 'u')
            ->andWhere('u = :user')
            ->andWhere('h.date BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $userIds
     * @return Holiday[]
     */
    public function findForUsersInMonth(array $userIds, DateTimeImmutable $monthStart, DateTimeImmutable $monthEndExclusive): array
    {
        if (empty($userIds)) {
            return [];
        }

        return $this->createQueryBuilder('h')
            ->leftJoin('h.users', 'u')->addSelect('u')
            ->andWhere('u.id IN (:uids)')->setParameter('uids', $userIds)
            ->andWhere('h.date >= :monthStart')->setParameter('monthStart', $monthStart)
            ->andWhere('h.date < :monthEndExcl')->setParameter('monthEndExcl', $monthEndExclusive)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
