<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findAdminEmails(): array
    {
        $emails = [];
        foreach ($this->findAll() as $user) {
            if ($user->getEmail() !== null && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $emails[] = $user->getEmail();
            }
        }

        sort($emails);
        return array_values(array_unique($emails));
    }

    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
