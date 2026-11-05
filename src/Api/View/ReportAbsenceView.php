<?php

namespace App\Api\View;

final readonly class ReportAbsenceView
{
    public function __construct(
        public string $typeKey,
        public string $typeName,
    ) {}
}
