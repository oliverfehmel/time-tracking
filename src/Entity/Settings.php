<?php

namespace App\Entity;

use App\Repository\SettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingsRepository::class)]
class Settings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $autoPauseAfterSixHours = 0;

    #[ORM\Column(type: 'integer')]
    private int $autoPauseAfterNineHours = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $usersAreAllowedToChangeTimeEntries = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoFilename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $faviconVersion = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAutoPauseAfterSixHours(): int
    {
        return $this->autoPauseAfterSixHours;
    }

    public function setAutoPauseAfterSixHours(int $autoPauseAfterSixHours): void
    {
        $this->autoPauseAfterSixHours = $autoPauseAfterSixHours;
    }

    public function getAutoPauseAfterNineHours(): int
    {
        return $this->autoPauseAfterNineHours;
    }

    public function setAutoPauseAfterNineHours(int $autoPauseAfterNineHours): void
    {
        $this->autoPauseAfterNineHours = $autoPauseAfterNineHours;
    }

    public function isUsersAreAllowedToChangeTimeEntries(): bool
    {
        return $this->usersAreAllowedToChangeTimeEntries;
    }

    public function setUsersAreAllowedToChangeTimeEntries(bool $usersAreAllowedToChangeTimeEntries): void
    {
        $this->usersAreAllowedToChangeTimeEntries = $usersAreAllowedToChangeTimeEntries;
    }

    public function getLogoFilename(): ?string
    {
        return $this->logoFilename;
    }

    public function setLogoFilename(?string $logoFilename): void
    {
        $this->logoFilename = $logoFilename;
    }

    public function getFaviconVersion(): ?string
    {
        return $this->faviconVersion;
    }

    public function setFaviconVersion(?string $faviconVersion): void
    {
        $this->faviconVersion = $faviconVersion;
    }
}
