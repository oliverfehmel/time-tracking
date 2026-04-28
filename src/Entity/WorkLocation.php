<?php

namespace App\Entity;

use App\Repository\WorkLocationRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkLocationRepository::class)]
#[ORM\Table(name: 'work_location')]
#[ORM\UniqueConstraint(name: 'uniq_work_location_user_date', columns: ['user_id', 'date'])]
class WorkLocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $date;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'work_location_type_id', nullable: false)]
    private WorkLocationType $locationType;

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

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(DateTimeImmutable $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getLocationType(): WorkLocationType
    {
        return $this->locationType;
    }

    public function setLocationType(WorkLocationType $locationType): self
    {
        $this->locationType = $locationType;
        return $this;
    }
}
