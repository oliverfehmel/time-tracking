<?php

namespace App\Api\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final class WorkLocationInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    #[OA\Property(description: 'ID of an active work location type')]
    public ?int $locationTypeId = null;
}
