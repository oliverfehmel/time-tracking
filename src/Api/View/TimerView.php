<?php

namespace App\Api\View;

use App\Entity\TimeEntry;

final readonly class TimerView
{
    public function __construct(
        public bool $running,
        public ?TimeEntryView $entry,
    ) {}

    public static function from(?TimeEntry $running): self
    {
        return new self($running !== null, $running === null ? null : TimeEntryView::from($running));
    }
}
