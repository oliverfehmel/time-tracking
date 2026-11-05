<?php

namespace App\Api\Dto;

use App\Entity\AbsenceType;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of POST /absence-types and PATCH /absence-types/{id} (merge patch).
 */
final class AbsenceTypeInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 80)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    #[Assert\Regex('/^[a-z0-9_-]+$/')]
    #[OA\Property(description: 'Unique slug, e.g. vacation, sick', example: 'vacation')]
    public string $key = '';

    public bool $active = true;

    public bool $requiresApproval = true;

    public bool $requiresQuota = true;

    #[OA\Property(description: 'Default yearly quota in days, max 366; 0.5 steps while half days are enabled; null = unlimited', example: 30)]
    public ?float $defaultYearlyQuotaDays = null;

    public bool $allowOverLimit = false;

    public static function fromType(AbsenceType $type): self
    {
        $input                         = new self();
        $input->name                   = $type->getName();
        $input->key                    = $type->getKeyName();
        $input->active                 = $type->isActive();
        $input->requiresApproval       = $type->requiresApproval();
        $input->requiresQuota          = $type->requiresQuota();
        $input->defaultYearlyQuotaDays = $type->getDefaultYearlyQuotaDays();
        $input->allowOverLimit         = $type->isAllowOverLimit();

        return $input;
    }
}
