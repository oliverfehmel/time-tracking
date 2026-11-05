<?php

namespace App\Tests\Entity;

use App\Entity\ApiToken;
use App\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ApiTokenTest extends TestCase
{
    public function testExpiry(): void
    {
        $never   = $this->token(null);
        $expires = $this->token(new DateTimeImmutable('2026-12-01 00:00:00'));

        self::assertFalse($never->isExpired(new DateTimeImmutable('2099-01-01')));
        self::assertFalse($expires->isExpired(new DateTimeImmutable('2026-11-30 23:59:59')));
        self::assertTrue($expires->isExpired(new DateTimeImmutable('2026-12-01 00:00:00')));
    }

    public function testLastUsedIsWrittenAtMostOncePerMinute(): void
    {
        $token = $this->token(null);

        self::assertTrue($token->markUsed(new DateTimeImmutable('2026-11-05 10:00:00')));
        self::assertFalse($token->markUsed(new DateTimeImmutable('2026-11-05 10:00:59')));
        self::assertSame('10:00:00', $token->getLastUsedAt()?->format('H:i:s'));
        self::assertTrue($token->markUsed(new DateTimeImmutable('2026-11-05 10:01:00')));
        self::assertSame('10:01:00', $token->getLastUsedAt()?->format('H:i:s'));
    }

    public function testScope(): void
    {
        self::assertFalse($this->token(null, ApiToken::SCOPE_READ)->allowsWrite());
        self::assertTrue($this->token(null, ApiToken::SCOPE_READ_WRITE)->allowsWrite());
    }

    private function token(?DateTimeImmutable $expiresAt, string $scope = ApiToken::SCOPE_READ): ApiToken
    {
        return new ApiToken(new User(), 'test', str_repeat('a', 64), 'tt_aaaaaaaa', $scope, new DateTimeImmutable('2026-11-01'), $expiresAt);
    }
}
