<?php

namespace App\Api\View;

final readonly class ReportWorkLocationView
{
    public function __construct(
        public string $name,
        public ?string $icon,
    ) {}
}
