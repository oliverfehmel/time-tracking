<?php

namespace App\Api\View;

use App\Api\ApiDates;
use App\Entity\Holiday;
use App\Entity\User;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'A non-working day for the users it is assigned to')]
final readonly class HolidayView
{
    /**
     * @param list<int>|null $userIds
     */
    public function __construct(
        public int $id,
        public string $name,
        #[OA\Property(format: 'date')]
        public string $date,
        #[OA\Property(description: 'Half-day holiday: only half of the target time is waived')]
        public bool $halfDay,
        #[OA\Property(description: '1.0 or 0.5')]
        public float $dayFraction,
        #[OA\Property(description: 'Assigned users; only returned to admins (null otherwise)')]
        public ?array $userIds,
    ) {}

    public static function from(Holiday $holiday, bool $withUsers): self
    {
        return new self(
            id: (int) $holiday->getId(),
            name: $holiday->getName(),
            date: (string) ApiDates::date($holiday->getDate()),
            halfDay: $holiday->isHalfDay(),
            dayFraction: $holiday->getDayFraction(),
            userIds: $withUsers
                ? array_values(array_map(static fn(User $u) => (int) $u->getId(), $holiday->getUsers()->toArray()))
                : null,
        );
    }
}
