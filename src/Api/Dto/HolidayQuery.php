<?php

namespace App\Api\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final class HolidayQuery extends PageQuery
{
    #[OA\Property(description: 'Regular users: only their own ID is allowed. Admins: holidays assigned to this user (default: all holidays).')]
    public ?int $userId = null;

    #[Assert\Date]
    #[OA\Property(description: 'First day (inclusive)', format: 'date')]
    public ?string $from = null;

    #[Assert\Date]
    #[OA\Property(description: 'Last day (inclusive)', format: 'date')]
    public ?string $to = null;
}
