<?php

namespace App\Api\View;

use App\Api\ApiDates;
use App\Entity\TimeEntry;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'A time entry; an entry without stoppedAt is a running timer')]
final readonly class TimeEntryView
{
    public function __construct(
        public int $id,
        public int $userId,
        #[OA\Property(format: 'date-time')]
        public string $startedAt,
        #[OA\Property(format: 'date-time')]
        public ?string $stoppedAt,
        public bool $running,
        #[OA\Property(description: 'Gross duration in seconds (before break deductions); null while running')]
        public ?int $durationSeconds,
    ) {}

    public static function from(TimeEntry $entry): self
    {
        $start = $entry->getStartedAt();
        $stop  = $entry->getStoppedAt();

        return new self(
            id: (int) $entry->getId(),
            userId: (int) $entry->getUser()?->getId(),
            startedAt: (string) ApiDates::dateTime($start),
            stoppedAt: ApiDates::dateTime($stop),
            running: $stop === null,
            durationSeconds: $stop === null ? null : $stop->getTimestamp() - $start->getTimestamp(),
        );
    }
}
