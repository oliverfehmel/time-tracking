<?php

namespace App\Api\Dto;

use OpenApi\Attributes as OA;

final class QuotaInput
{
    #[OA\Property(description: 'Days, max 366; 0.5 steps while half days are enabled; null = use the type default', example: 27.5)]
    public ?float $quotaDays = null;

    #[OA\Property(description: 'Override the type\'s over-limit setting; null = use the type setting')]
    public ?bool $allowOverLimit = null;
}
