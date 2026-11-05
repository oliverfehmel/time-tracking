<?php

namespace App\Api\View;

use App\Entity\AbsenceQuota;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Individual quota of a user for one absence type and year')]
final readonly class AbsenceQuotaView
{
    public function __construct(
        public int $userId,
        public int $typeId,
        public int $year,
        #[OA\Property(description: 'Days (0.5 steps); null = use the type default')]
        public ?float $quotaDays,
        #[OA\Property(description: 'Override of the type\'s over-limit setting; null = use the type setting')]
        public ?bool $allowOverLimit,
    ) {}

    public static function from(AbsenceQuota $quota): self
    {
        return new self(
            userId: (int) $quota->getUser()->getId(),
            typeId: (int) $quota->getType()->getId(),
            year: $quota->getYear(),
            quotaDays: $quota->getQuotaDays(),
            allowOverLimit: $quota->isAllowOverLimit(),
        );
    }
}
