<?php

namespace App\Api\View;

use App\Entity\User;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'A user account')]
final readonly class UserView
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
        #[OA\Property(description: 'Has administrator rights')]
        public bool $admin,
        #[OA\Property(description: 'Stored roles plus ROLE_USER (the role hierarchy, e.g. ROLE_ALLOWED_TO_SWITCH, is not expanded)')]
        public array $roles,
        #[OA\Property(description: 'Daily target working time in minutes')]
        public int $dailyWorkMinutes,
    ) {}

    public static function from(User $user): self
    {
        $roles = array_values($user->getRoles());

        return new self(
            id: (int) $user->getId(),
            email: (string) $user->getEmail(),
            name: $user->getName(),
            admin: in_array('ROLE_ADMIN', $roles, true),
            roles: $roles,
            dailyWorkMinutes: $user->getDailyWorkMinutes(),
        );
    }
}
