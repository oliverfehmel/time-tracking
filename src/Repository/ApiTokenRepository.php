<?php

namespace App\Repository;

use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<ApiToken>
 */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    public function findOneByHash(string $tokenHash): ?ApiToken
    {
        return $this->createQueryBuilder('t')
            ->addSelect('u')->join('t.user', 'u')
            ->andWhere('t.tokenHash = :hash')->setParameter('hash', $tokenHash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ApiToken[]
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :u')->setParameter('u', $user)
            ->orderBy('t.createdAt', SortDirection::Descending)
            ->addOrderBy('t.id', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }
}
