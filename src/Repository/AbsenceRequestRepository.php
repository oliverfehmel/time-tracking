<?php

namespace App\Repository;

use App\Entity\AbsenceRequest;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AbsenceRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbsenceRequest::class);
    }

    /** @return AbsenceRequest[] */
    public function findForUserInYear(User $user, int $year): array
    {
        $from = new DateTimeImmutable(sprintf('%04d-01-01', $year));
        $to   = new DateTimeImmutable(sprintf('%04d-12-31', $year));

        return $this->createQueryBuilder('a')
            ->andWhere('a.requestedBy = :u')->setParameter('u', $user)
            ->andWhere('a.startDate <= :to')->setParameter('to', $to)
            ->andWhere('a.endDate >= :from')->setParameter('from', $from)
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * @return AbsenceRequest[]
     */
    public function findPendingApprovals(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :s')->setParameter('s', AbsenceRequest::STATUS_PENDING)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()->getResult();
    }

    public function hasOverlappingApprovedOrPending(User $user, $start, $end): bool
    {
        $count = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.requestedBy = :u')->setParameter('u', $user)
            ->andWhere('a.status IN (:st)')->setParameter('st', [AbsenceRequest::STATUS_PENDING, AbsenceRequest::STATUS_APPROVED])
            ->andWhere('a.startDate <= :end')->setParameter('end', $end)
            ->andWhere('a.endDate >= :start')->setParameter('start', $start)
            ->getQuery()->getSingleScalarResult();

        return $count > 0;
    }

    public function findApprovedForUserBetween(User $user, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.requestedBy = :u')->setParameter('u', $user)
            ->andWhere('a.status = :s')->setParameter('s', AbsenceRequest::STATUS_APPROVED)
            ->andWhere('a.startDate < :to')->setParameter('to', $to)     // overlap check
            ->andWhere('a.endDate >= :from')->setParameter('from', $from)
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * @param int[] $userIds
     * @param string[] $statuses
     * @return AbsenceRequest[]
     */
    public function findOverlappingForUsersAndMonth(array $userIds, DateTimeImmutable $monthStart,
                                                    DateTimeImmutable $monthEndInclusive, array $statuses): array {
        if (empty($userIds)) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->leftJoin('a.requestedBy', 'u')->addSelect('u')
            ->leftJoin('a.type', 't')->addSelect('t')
            ->andWhere('u.id IN (:uids)')->setParameter('uids', $userIds)
            ->andWhere('a.startDate <= :monthEnd')->setParameter('monthEnd', $monthEndInclusive)
            ->andWhere('a.endDate >= :monthStart')->setParameter('monthStart', $monthStart)
            ->andWhere('a.status IN (:statuses)')->setParameter('statuses', $statuses)
            ->orderBy('u.id', 'ASC')
            ->addOrderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findForUserOverlappingRangeWithStatuses(User $user, DateTimeImmutable $from, DateTimeImmutable $toExclusive, array $statuses): array
    {
        $toInclusive = $toExclusive->modify('-1 day');

        return $this->createQueryBuilder('r')
            ->addSelect('t')->join('r.type', 't')
            ->andWhere('r.requestedBy = :u')->setParameter('u', $user)
            ->andWhere('r.status IN (:st)')->setParameter('st', $statuses)
            ->andWhere('r.startDate <= :toInc')->setParameter('toInc', $toInclusive)
            ->andWhere('r.endDate >= :from')->setParameter('from', $from)
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('r.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findForUserOverlappingRangeAllTypes(User $user, DateTimeImmutable $from, DateTimeImmutable $toExclusive, array $statuses): array
    {
        $toInclusive = $toExclusive->modify('-1 day');

        return $this->createQueryBuilder('r')
            ->addSelect('t')->join('r.type', 't')
            ->andWhere('r.requestedBy = :u')->setParameter('u', $user)
            ->andWhere('r.status IN (:st)')->setParameter('st', $statuses)
            ->andWhere('r.startDate <= :toInc')->setParameter('toInc', $toInclusive)
            ->andWhere('r.endDate >= :from')->setParameter('from', $from)
            ->orderBy('r.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findProcessedApprovals(): array
    {
        return $this->createQueryBuilder('ar')
            ->addSelect('requestedBy')
            ->addSelect('type')
            ->addSelect('approvedBy')
            ->leftJoin('ar.requestedBy', 'requestedBy')
            ->leftJoin('ar.type', 'type')
            ->leftJoin('ar.approvedBy', 'approvedBy')
            ->andWhere('ar.status IN (:statuses)')
            ->setParameter('statuses', [
                AbsenceRequest::STATUS_APPROVED,
                AbsenceRequest::STATUS_REJECTED,
            ])
            ->orderBy('ar.approvedAt', 'DESC')
            ->addOrderBy('ar.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
