<?php

namespace App\Entity;

use App\Repository\ApiTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Personal API token. Only the SHA-256 hash of the token is stored; the plain token is shown once on creation.
 * Revoking a token deletes the row.
 */
#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
#[ORM\Table(name: 'api_token')]
#[ORM\UniqueConstraint(name: 'uniq_api_token_hash', columns: ['token_hash'])]
class ApiToken
{
    public const string PREFIX           = 'tt_';
    public const string SCOPE_READ       = 'read';
    public const string SCOPE_READ_WRITE = 'read_write';
    public const array SCOPES            = [self::SCOPE_READ, self::SCOPE_READ_WRITE];

    /** lastUsedAt is written at most this often to avoid a database write per API request */
    private const int LAST_USED_RESOLUTION_SECONDS = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,

        #[ORM\Column(length: 100)]
        private string $name,

        #[ORM\Column(length: 64)]
        private string $tokenHash,

        #[ORM\Column(length: 16)]
        private string $tokenPrefix,

        #[ORM\Column(length: 20)]
        private string $scope,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $expiresAt = null,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    /**
     * First characters of the plain token, so users can tell their tokens apart.
     */
    public function getTokenPrefix(): string
    {
        return $this->tokenPrefix;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function allowsWrite(): bool
    {
        return $this->scope === self::SCOPE_READ_WRITE;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= $now;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    /**
     * @return bool whether lastUsedAt changed (and needs to be flushed)
     */
    public function markUsed(DateTimeImmutable $now): bool
    {
        if ($this->lastUsedAt !== null && $now->getTimestamp() - $this->lastUsedAt->getTimestamp() < self::LAST_USED_RESOLUTION_SECONDS) {
            return false;
        }

        $this->lastUsedAt = $now;

        return true;
    }
}
