<?php

namespace App\Entity;

use App\Repository\HolidayRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HolidayRepository::class)]
#[ORM\Table(name: 'holiday')]
#[ORM\UniqueConstraint(name: 'uniq_holiday_date_name', columns: ['date', 'name'])]
class Holiday
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $date;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'holidays')]
    #[ORM\JoinTable(name: 'holiday_user')]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->date = new DateTimeImmutable('today');
    }

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

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(DateTimeImmutable $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }
        return $this;
    }

    public function removeUser(User $user): self
    {
        $this->users->removeElement($user);
        return $this;
    }
}
