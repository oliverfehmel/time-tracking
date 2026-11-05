<?php

namespace App\Api\View;

use App\Entity\AbsenceType;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'An absence category such as vacation or sick leave')]
final readonly class AbsenceTypeView
{
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public bool $active,
        public bool $requiresApproval,
        public bool $requiresQuota,
        #[OA\Property(description: 'Default yearly quota in days (0.5 steps); null = unlimited')]
        public ?float $defaultYearlyQuotaDays,
        #[OA\Property(description: 'Requests may exceed the quota (individual quotas can override this)')]
        public bool $allowOverLimit,
    ) {}

    public static function from(AbsenceType $type): self
    {
        return new self(
            id: (int) $type->getId(),
            key: $type->getKeyName(),
            name: $type->getName(),
            active: $type->isActive(),
            requiresApproval: $type->requiresApproval(),
            requiresQuota: $type->requiresQuota(),
            defaultYearlyQuotaDays: $type->getDefaultYearlyQuotaDays(),
            allowOverLimit: $type->isAllowOverLimit(),
        );
    }
}
