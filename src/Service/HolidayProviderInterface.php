<?php

namespace App\Service;

use DateTimeImmutable;

interface HolidayProviderInterface
{
    public function isHoliday(DateTimeImmutable $date, ?string $region = null): bool;
}
