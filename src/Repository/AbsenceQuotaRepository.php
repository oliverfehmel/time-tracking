<?php

namespace App\Repository;

use App\Entity\AbsenceQuota;
use App\Entity\AbsenceType;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AbsenceQuotaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbsenceQuota::class);
    }

    public function findOneFor(User $user, AbsenceType $type, int $year): ?object
    {
        return $this->findOneBy(['user' => $user, 'type' => $type, 'year' => $year]);
    }

    /**
     * @return AbsenceQuota[]
     */
    public function findForUserInYear(User $user, int $year): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.user = :u')->setParameter('u', $user)
            ->andWhere('q.year = :y')->setParameter('y', $year)
            ->getQuery()
            ->getResult();
    }

    public function findForUserYear(User $user, int $year): array
    {
        return $this->createQueryBuilder('q')
            ->addSelect('t')->join('q.type', 't')
            ->andWhere('q.user = :u')->setParameter('u', $user)
            ->andWhere('q.year = :y')->setParameter('y', $year)
            ->getQuery()
            ->getResult();
    }
}
