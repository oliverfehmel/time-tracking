<?php

namespace App\Api\Dto;

use App\Entity\AbsenceRequest;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final class AbsenceRequestQuery extends PageQuery
{
    #[OA\Property(description: 'Regular users: only their own ID is allowed. Admins: filter by user (default: all users).')]
    public ?int $userId = null;

    #[Assert\Range(min: 1970, max: 2999)]
    #[OA\Property(description: 'Requests overlapping this calendar year')]
    public ?int $year = null;

    #[Assert\Choice(choices: [AbsenceRequest::STATUS_PENDING, AbsenceRequest::STATUS_APPROVED, AbsenceRequest::STATUS_REJECTED, AbsenceRequest::STATUS_CANCELLED])]
    public ?string $status = null;
}
