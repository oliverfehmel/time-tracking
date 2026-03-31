<?php

namespace App\Entity;

use App\Repository\AbsenceTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AbsenceTypeRepository::class)]
#[ORM\Table(name: 'absence_type')]
class AbsenceType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    private string $name;

    #[ORM\Column(length: 30, unique: true)]
    private string $keyName; // e.g. "vacation", "sick"

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $requiresApproval = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $requiresQuota = true; // Krankheit z.B. false

    #[ORM\Column(nullable: true)]
    private ?int $defaultYearlyQuotaDays = null; // null = unlimited

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getKeyName(): string
    {
        return $this->keyName;
    }

    public function setKeyName(string $keyName): self
    {
        $this->keyName = $keyName;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function requiresApproval(): bool
    {
        return $this->requiresApproval;
    }

    public function setRequiresApproval(bool $requiresApproval): self
    {
        $this->requiresApproval = $requiresApproval;
        return $this;
    }

    public function requiresQuota(): bool
    {
        return $this->requiresQuota;
    }

    public function setRequiresQuota(bool $requiresQuota): self
    {
        $this->requiresQuota = $requiresQuota;
        return $this;
    }

    public function getDefaultYearlyQuotaDays(): ?int
    {
        return $this->defaultYearlyQuotaDays;
    }

    public function setDefaultYearlyQuotaDays(?int $days): self
    {
        $this->defaultYearlyQuotaDays = $days;
        return $this;
    }
}
