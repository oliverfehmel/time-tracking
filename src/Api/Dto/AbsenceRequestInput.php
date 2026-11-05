<?php

namespace App\Api\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final class AbsenceRequestInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    #[OA\Property(description: 'ID of an active absence type')]
    public ?int $typeId = null;

    #[Assert\NotBlank]
    #[Assert\Date]
    #[OA\Property(format: 'date', example: '2026-12-24')]
    public string $startDate = '';

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Date]
    #[OA\Property(description: 'Default: startDate', format: 'date', example: '2026-12-24')]
    public ?string $endDate = null;

    #[OA\Property(description: 'Half day; only for single-day requests and only while half days are enabled')]
    public bool $halfDay = false;

    #[Assert\Length(max: 2000)]
    public ?string $comment = null;

    #[OA\Property(description: 'Admins only: request on behalf of this user (default: you)')]
    public ?int $userId = null;
}
