<?php

namespace App\Service;

use App\Entity\AbsenceRequest;
use App\Entity\User;
use App\Repository\HolidayRepository;
use DateTimeImmutable;

final readonly class AbsenceDayCalculator
{
    public function __construct(
        private HolidayRepository $holidayRepository,
        private ?HolidayProviderInterface $holidayProvider = null,
    ) {}

    public function countChargeableDays(DateTimeImmutable $start, DateTimeImmutable $end, ?string $holidayRegion = null,
        ?User $user = null): int
    {
        if ($end < $start) {
            return 0;
        }

        $userHolidayDates = [];
        if ($user !== null) {
            $holidays = $this->holidayRepository->findForUserBetween($user, $start, $end);

            foreach ($holidays as $holiday) {
                $userHolidayDates[$holiday->getDate()->format('Y-m-d')] = true;
            }
        }

        $days = 0;
        $cur = $start;

        while ($cur <= $end) {
            $weekday = (int) $cur->format('N'); // 1=Mon ... 7=Sun
            $isWeekend = $weekday >= 6;

            $isGlobalHoliday = false;
            if ($this->holidayProvider) {
                $isGlobalHoliday = $this->holidayProvider->isHoliday($cur, $holidayRegion);
            }

            $isUserHoliday = isset($userHolidayDates[$cur->format('Y-m-d')]);

            if (!$isWeekend && !$isGlobalHoliday && !$isUserHoliday) {
                $days++;
            }

            $cur = $cur->modify('+1 day');
        }

        return $days;
    }

    public function countWorkdaysForRequest(
        AbsenceRequest $request,
        DateTimeImmutable $rangeStart,
        DateTimeImmutable $rangeEndExclusive,
        array $holidayDates,
    ): int {
        $start       = max($request->getStartDate(), $rangeStart);
        $endExclusive = min($request->getEndDate()->modify('+1 day'), $rangeEndExclusive);

        $sum = 0;
        for ($d = $start; $d < $endExclusive; $d = $d->modify('+1 day')) {
            $dow = (int) $d->format('N');
            if ($dow >= 6 || isset($holidayDates[$d->format('Y-m-d')])) {
                continue;
            }
            $sum++;
        }
        return $sum;
    }
}
