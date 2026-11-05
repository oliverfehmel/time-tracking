<?php

namespace App\Repository;

use App\Entity\WorkLocationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

class WorkLocationTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkLocationType::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = true')
            ->orderBy('t.name', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    public function findDefault(): ?WorkLocationType
    {
        return $this->findOneBy(['isDefault' => true]);
    }
}
