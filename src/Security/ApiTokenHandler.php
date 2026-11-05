<?php

namespace App\Security;

use App\Entity\ApiToken;
use App\Repository\ApiTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Resolves `Authorization: Bearer tt_…` to the token's owner. The token itself is stored as request attribute
 * {@see self::REQUEST_ATTRIBUTE} so later listeners can check its scope.
 */
final readonly class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public const string REQUEST_ATTRIBUTE = '_api_token';

    public function __construct(
        private ApiTokenRepository $tokenRepo,
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        private ClockInterface $clock,
    ) {}

    public function getUserBadgeFrom(#[SensitiveParameter] string $accessToken): UserBadge
    {
        if (!str_starts_with($accessToken, ApiToken::PREFIX)) {
            throw new BadCredentialsException('Invalid API token.');
        }

        $now   = DateTimeImmutable::createFromInterface($this->clock->now());
        $token = $this->tokenRepo->findOneByHash(ApiTokenManager::hash($accessToken));

        if ($token === null || $token->isExpired($now)) {
            throw new BadCredentialsException('Invalid or expired API token.');
        }

        if ($token->markUsed($now)) {
            $this->em->flush();
        }

        $this->requestStack->getCurrentRequest()?->attributes->set(self::REQUEST_ATTRIBUTE, $token);

        $user = $token->getUser();

        return new UserBadge($user->getUserIdentifier(), static fn() => $user);
    }
}
