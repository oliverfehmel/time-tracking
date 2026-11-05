<?php

namespace App\Api\Dto;

use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of POST /users and PATCH /users/{id} (merge patch: send only the fields to change).
 */
final class UserInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\Range(min: 0, max: 1440)]
    #[OA\Property(description: 'Daily target working time in minutes')]
    public int $dailyWorkMinutes = 0;

    #[OA\Property(description: 'Grant administrator rights')]
    public bool $admin = false;

    #[Assert\Length(min: 8, max: 4096)]
    #[OA\Property(description: 'Write-only. Required on create, optional on update.', minLength: 8)]
    public ?string $password = null;

    public static function fromUser(User $user): self
    {
        $input                   = new self();
        $input->email            = (string) $user->getEmail();
        $input->name             = $user->getName();
        $input->dailyWorkMinutes = $user->getDailyWorkMinutes();
        $input->admin            = in_array('ROLE_ADMIN', $user->getRoles(), true);

        return $input;
    }
}
