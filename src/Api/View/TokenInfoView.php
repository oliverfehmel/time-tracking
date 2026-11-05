<?php

namespace App\Api\View;

use OpenApi\Attributes as OA;

final readonly class TokenInfoView
{
    public function __construct(
        public string $name,
        #[OA\Property(enum: ['read', 'read_write'])]
        public string $scope,
        #[OA\Property(description: 'null = never expires', format: 'date-time')]
        public ?string $expiresAt,
    ) {}
}
