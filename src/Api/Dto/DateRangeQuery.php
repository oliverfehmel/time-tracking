<?php

namespace App\Api\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final class DateRangeQuery extends PageQuery
{
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Date]
    #[OA\Property(description: 'First day (inclusive)', format: 'date')]
    public ?string $from = null;

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Date]
    #[OA\Property(description: 'Last day (inclusive)', format: 'date')]
    public ?string $to = null;
}
