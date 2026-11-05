<?php

namespace App\Api\View;

use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Worked time (Ist), target time (Soll) and balance of one user and year. Soll is capped to today, so future days do not create a deficit.')]
final readonly class TimeReportView
{
    /**
     * @param list<TimeReportMonthView> $months
     */
    public function __construct(
        public int $userId,
        public int $year,
        #[OA\Property(description: 'Net worked seconds of the year (after break deductions)')]
        public int $istSeconds,
        public int $sollSeconds,
        #[OA\Property(description: 'istSeconds - sollSeconds')]
        public int $deltaSeconds,
        public array $months,
    ) {}

    /**
     * @param list<array<string,mixed>> $months rows of {@see \App\Service\UserYearReportBuilder::build()}
     */
    public static function from(int $userId, int $year, array $months): self
    {
        $monthViews = array_values(array_map(TimeReportMonthView::from(...), $months));
        $ist        = array_sum(array_map(static fn(TimeReportMonthView $m) => $m->istSeconds, $monthViews));
        $soll       = array_sum(array_map(static fn(TimeReportMonthView $m) => $m->sollSeconds, $monthViews));

        return new self($userId, $year, $ist, $soll, $ist - $soll, $monthViews);
    }
}
