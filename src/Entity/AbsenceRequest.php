<?php

namespace App\Entity;

use App\Repository\AbsenceRequestRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AbsenceRequestRepository::class)]
#[ORM\Table(name: 'absence_request')]
#[ORM\Index(name: 'idx_absence_status', columns: ['status'])]
#[ORM\Index(name: 'idx_absence_start', columns: ['start_date'])]
class AbsenceRequest
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $requestedBy;

    #[ORM\ManyToOne(targetEntity: AbsenceType::class)]
    #[ORM\JoinColumn(nullable: false)]
    private AbsenceType $type;

    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $startDate;

    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $endDate;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $approvedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequestedBy(): User
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(User $u): self
    {
        $this->requestedBy = $u;
        return $this;
    }

    public function getType(): AbsenceType
    {
        return $this->type;
    }

    public function setType(AbsenceType $t): self
    {
        $this->type = $t;
        return $this;
    }

    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(DateTimeImmutable $d): self
    {
        $this->startDate = $d;
        return $this;
    }

    public function getEndDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(DateTimeImmutable $d): self
    {
        $this->endDate = $d;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $s): self
    {
        $this->status = $s;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $c): self
    {
        $this->comment = $c;
        return $this;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function getApprovedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function approve(User $admin): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->approvedBy = $admin;
        $this->approvedAt = new DateTimeImmutable('now');
        $this->rejectReason = null;
    }

    public function reject(User $admin, string $reason): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->approvedBy = $admin;
        $this->approvedAt = new DateTimeImmutable('now');
        $this->rejectReason = $reason;
    }

    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELLED;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRejectReason(): ?string
    {
        return $this->rejectReason;
    }
}
