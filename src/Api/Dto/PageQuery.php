<?php

namespace App\Api\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Pagination parameters shared by all list endpoints.
 */
class PageQuery
{
    public const int MAX_PER_PAGE = 200;

    #[Assert\Positive]
    #[OA\Property(description: 'Page number, starting at 1', minimum: 1)]
    public int $page = 1;

    #[Assert\Range(min: 1, max: self::MAX_PER_PAGE)]
    #[OA\Property(description: 'Items per page', maximum: self::MAX_PER_PAGE, minimum: 1)]
    public int $perPage = 50;

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
