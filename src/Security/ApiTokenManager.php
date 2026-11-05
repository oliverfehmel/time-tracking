<?php

namespace App\Security;

use App\Entity\ApiToken;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

/**
 * Creates, hashes and revokes personal API tokens.
 */
final readonly class ApiTokenManager
{
    /** Allowed validity periods in days; null = never expires */
    public const array VALIDITY_DAYS = [30, 90, 365, null];

    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {}

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * @return array{0: ApiToken, 1: string} the persisted token and the plain token (to be shown exactly once)
     */
    public function create(User $owner, string $name, string $scope, ?int $validityDays): array
    {
        if (!in_array($scope, ApiToken::SCOPES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown API token scope "%s".', $scope));
        }
        if (!in_array($validityDays, self::VALIDITY_DAYS, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported validity of %s days.', var_export($validityDays, true)));
        }

        $now   = DateTimeImmutable::createFromInterface($this->clock->now());
        $plain = ApiToken::PREFIX . bin2hex(random_bytes(32));

        $token = new ApiToken(
            user: $owner,
            name: trim($name),
            tokenHash: self::hash($plain),
            tokenPrefix: substr($plain, 0, 11),
            scope: $scope,
            createdAt: $now,
            expiresAt: $validityDays === null ? null : $now->modify(sprintf('+%d days', $validityDays)),
        );

        $this->em->persist($token);
        $this->em->flush();

        return [$token, $plain];
    }

    public function revoke(ApiToken $token): void
    {
        $this->em->remove($token);
        $this->em->flush();
    }
}
