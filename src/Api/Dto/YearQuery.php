<?php

namespace App\Api\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final class YearQuery
{
    #[Assert\Range(min: 1970, max: 2999)]
    #[OA\Property(description: 'Default: current year')]
    public ?int $year = null;
}
