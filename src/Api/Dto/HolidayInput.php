<?php

namespace App\Api\Dto;

use App\Api\ApiDates;
use App\Entity\Holiday;
use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of POST /holidays and PATCH /holidays/{id} (merge patch).
 */
final class HolidayInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 255)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Date]
    #[OA\Property(format: 'date', example: '2026-12-24')]
    public string $date = '';

    #[OA\Property(description: 'Half-day holiday. Can only be switched on while half days are enabled; an existing half-day holiday keeps its flag.')]
    public bool $halfDay = false;

    /** @var list<int> */
    #[Assert\All([new Assert\Positive()])]
    #[OA\Property(description: 'IDs of the users this holiday applies to', type: 'array', items: new OA\Items(type: 'integer'))]
    public array $userIds = [];

    public static function fromHoliday(Holiday $holiday): self
    {
        $input          = new self();
        $input->name    = $holiday->getName();
        $input->date    = (string) ApiDates::date($holiday->getDate());
        $input->halfDay = $holiday->isHalfDay();
        $input->userIds = array_values(array_map(static fn(User $u) => (int) $u->getId(), $holiday->getUsers()->toArray()));

        return $input;
    }
}
