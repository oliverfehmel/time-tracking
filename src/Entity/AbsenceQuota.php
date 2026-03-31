<?php

namespace App\Entity;

use App\Repository\AbsenceQuotaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AbsenceQuotaRepository::class)]
#[ORM\Table(name: 'absence_quota')]
#[ORM\UniqueConstraint(name: 'uniq_user_type_year', columns: ['user_id', 'absence_type_id', 'year'])]
class AbsenceQuota
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: AbsenceType::class)]
    #[ORM\JoinColumn(name: 'absence_type_id', referencedColumnName: 'id', nullable: false)]
    private AbsenceType $type;

    #[ORM\Column]
    private int $year;

    #[ORM\Column(nullable: true)]
    private ?int $quotaDays = null; // null = unlimited

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getType(): AbsenceType
    {
        return $this->type;
    }

    public function setType(AbsenceType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;
        return $this;
    }

    public function getQuotaDays(): ?int
    {
        return $this->quotaDays;
    }

    public function setQuotaDays(?int $quotaDays): self
    {
        $this->quotaDays = $quotaDays;
        return $this;
    }
}
