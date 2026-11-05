<?php

namespace App\Api\View;

use App\Entity\WorkLocationType;
use OpenApi\Attributes as OA;

final readonly class WorkLocationTypeView
{
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        #[OA\Property(description: 'FontAwesome class string')]
        public ?string $icon,
        public bool $active,
        #[OA\Property(description: 'Used for days with time entries but without an explicit work location')]
        public bool $default,
    ) {}

    public static function from(WorkLocationType $type): self
    {
        return new self(
            id: (int) $type->getId(),
            key: $type->getKeyName(),
            name: $type->getName(),
            icon: $type->getIcon(),
            active: $type->isActive(),
            default: $type->isDefault(),
        );
    }
}
