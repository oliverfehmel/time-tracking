<?php

namespace App\Api\View;

use App\Api\ApiDates;
use OpenApi\Attributes as OA;

final readonly class TimeReportDayView
{
    public function __construct(
        #[OA\Property(format: 'date')]
        public string $date,
        #[OA\Property(description: 'Monday to Friday')]
        public bool $workday,
        #[OA\Property(description: 'Start of the first entry on this day', format: 'date-time')]
        public ?string $firstStart,
        #[OA\Property(description: 'End of the last entry on this day (now for a running timer)', format: 'date-time')]
        public ?string $lastEnd,
        #[OA\Property(description: 'Net worked seconds (after break deductions)')]
        public int $istSeconds,
        public int $sollSeconds,
        public int $deltaSeconds,
        #[OA\Property(description: 'Share of the day covered by holidays: 0, 0.5 or 1')]
        public float $holidayFraction,
        #[OA\Property(description: 'Share of the day covered by approved absences: 0, 0.5 or 1')]
        public float $absenceFraction,
        #[OA\Property(description: 'Remaining share of a workday that counts towards Soll')]
        public float $workFraction,
        #[OA\Property(description: 'Approved absence on this day (two half days of different types are joined with " / ")')]
        public ?ReportAbsenceView $absence,
        #[OA\Property(description: 'Explicit work location, or the default one on workdays with entries')]
        public ?ReportWorkLocationView $workLocation,
    ) {}

    /**
     * @param array<string,mixed> $day
     */
    public static function from(array $day): self
    {
        return new self(
            date: (string) ApiDates::date($day['date']),
            workday: $day['isWorkday'],
            firstStart: ApiDates::dateTime($day['start']),
            lastEnd: ApiDates::dateTime($day['end']),
            istSeconds: $day['netSeconds'],
            sollSeconds: $day['sollSeconds'],
            deltaSeconds: $day['deltaSeconds'],
            holidayFraction: (float) $day['holidayFraction'],
            absenceFraction: (float) $day['absenceFraction'],
            workFraction: (float) $day['workFraction'],
            absence: $day['absenceTypeKey'] !== null ? new ReportAbsenceView($day['absenceTypeKey'], $day['absenceTypeName']) : null,
            workLocation: $day['workLocationName'] !== null ? new ReportWorkLocationView($day['workLocationName'], $day['workLocationIcon']) : null,
        );
    }
}
