<?php

namespace App\Api\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final class TimeEntryQuery extends PageQuery
{
    #[OA\Property(description: 'Admins only: entries of this user (default: you)')]
    public ?int $userId = null;

    #[Assert\Date]
    #[OA\Property(description: 'Entries overlapping [from, to): first day (inclusive)', format: 'date')]
    public ?string $from = null;

    #[Assert\Date]
    #[OA\Property(description: 'Last day (exclusive)', format: 'date')]
    public ?string $to = null;
}
