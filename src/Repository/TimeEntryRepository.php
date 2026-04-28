<?php

namespace App\Repository;

use App\Entity\TimeEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TimeEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeEntry::class);
    }

    public function findRunningForUser(User $user): ?TimeEntry
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :u')->setParameter('u', $user)
            ->andWhere('t.stoppedAt IS NULL')
            ->orderBy('t.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findForUserBetween(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :u')->setParameter('u', $user)
            ->andWhere('t.startedAt < :to')->setParameter('to', $to)
            ->andWhere('(t.stoppedAt IS NULL OR t.stoppedAt > :from)')->setParameter('from', $from)
            ->orderBy('t.startedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TimeEntry[]
     */
    public function findOverlappingForUser(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to, ?TimeEntry $exclude = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.user = :u')->setParameter('u', $user)
            ->andWhere('t.startedAt < :to')->setParameter('to', $to)
            ->andWhere('(t.stoppedAt IS NULL OR t.stoppedAt > :from)')->setParameter('from', $from)
            ->orderBy('t.startedAt', 'ASC');

        if ($exclude?->getId() !== null) {
            $qb->andWhere('t.id != :excludeId')->setParameter('excludeId', $exclude->getId());
        }

        return $qb->getQuery()->getResult();
    }
}
