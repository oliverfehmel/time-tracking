<?php

namespace App\Repository;

use App\Entity\AbsenceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AbsenceTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbsenceType::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = 1')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = true')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
