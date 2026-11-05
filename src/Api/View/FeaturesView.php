<?php

namespace App\Api\View;

use OpenApi\Attributes as OA;

final readonly class FeaturesView
{
    public function __construct(
        #[OA\Property(description: 'Half-day holidays, absence requests and quotas can be entered')]
        public bool $halfDaysEnabled,
        #[OA\Property(description: 'Regular users may change time entries and work locations (current and previous month)')]
        public bool $timeEntryEditingAllowed,
    ) {}
}
