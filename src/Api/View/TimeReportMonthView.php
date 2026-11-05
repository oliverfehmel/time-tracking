<?php

namespace App\Api\View;

use OpenApi\Attributes as OA;

final readonly class TimeReportMonthView
{
    /**
     * @param list<TimeReportDayView> $days
     */
    public function __construct(
        #[OA\Property(description: 'YYYY-MM', example: '2026-11')]
        public string $month,
        public int $istSeconds,
        public int $sollSeconds,
        public int $deltaSeconds,
        #[OA\Property(description: 'Regular users may still change entries of this month')]
        public bool $editable,
        public array $days,
    ) {}

    /**
     * @param array<string,mixed> $month
     */
    public static function from(array $month): self
    {
        return new self(
            month: $month['key'],
            istSeconds: $month['istSeconds'],
            sollSeconds: $month['sollSeconds'],
            deltaSeconds: $month['deltaSeconds'],
            editable: $month['editable'],
            days: array_values(array_map(TimeReportDayView::from(...), $month['days'])),
        );
    }
}
