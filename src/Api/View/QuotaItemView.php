<?php

namespace App\Api\View;

use OpenApi\Attributes as OA;

final readonly class QuotaItemView
{
    public function __construct(
        public AbsenceTypeRefView $type,
        #[OA\Property(description: 'Quota in days (individual quota, otherwise the type default); null = unlimited or no quota')]
        public ?float $quotaDays,
        public bool $allowOverLimit,
        public float $usedApprovedDays,
        public float $usedPendingDays,
        #[OA\Property(description: 'quotaDays minus approved days, never negative; null = unlimited')]
        public ?float $remainingDays,
    ) {}
}
