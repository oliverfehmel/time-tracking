<?php

namespace App\Api\View;

use App\Entity\AbsenceType;

final readonly class AbsenceTypeRefView
{
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
    ) {}

    public static function from(AbsenceType $type): self
    {
        return new self((int) $type->getId(), $type->getKeyName(), $type->getName());
    }
}
