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

    /**
     * Zählt die anrechenbaren Abwesenheitstage in [start, end] (inklusive).
     * Wochenenden zählen nicht, Feiertage reduzieren den Tag um ihren Anteil (halber Feiertag → max. 0,5).
     *
     * @param float $dayFraction Anteil pro Tag, den die Abwesenheit abdeckt (1.0 = ganzer Tag, 0.5 = halber Tag)
     */
    public function countChargeableDays(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?string $holidayRegion = null,
        ?User $user = null,
        float $dayFraction = DayPortion::FULL,
    ): float {
        if ($end < $start) {
            return 0.0;
        }

        $userHolidays = [];
        if ($user !== null) {
            $userHolidays = DayPortion::holidayMap($this->holidayRepository->findForUserBetween($user, $start, $end));
        }

        $days = 0.0;
        for ($cur = $start; $cur <= $end; $cur = $cur->modify('+1 day')) {
            if ((int) $cur->format('N') >= 6) { // 6=Sat, 7=Sun
                continue;
            }

            $holiday = $userHolidays[$cur->format('Y-m-d')] ?? 0.0;
            if ($this->holidayProvider?->isHoliday($cur, $holidayRegion)) {
                $holiday = DayPortion::FULL;
            }

            $days += DayPortion::chargeableFraction($holiday, $dayFraction);
        }

        return $days;
    }

    /**
     * Anrechenbare Tage eines Antrags unter Berücksichtigung der Feiertage des Antragstellers und eines halben Tages.
     */
    public function countForRequest(AbsenceRequest $request, ?string $holidayRegion = null): float
    {
        return $this->countChargeableDays(
            $request->getStartDate(),
            $request->getEndDate(),
            $holidayRegion,
            $request->getRequestedBy(),
            $request->getDayFraction(),
        );
    }

    /**
     * Anrechenbare Tage eines Antrags innerhalb von [rangeStart, rangeEndExclusive).
     *
     * @param array<string,float|bool> $holidayFractions keyed by 'Y-m-d'; Wert = Feiertagsanteil (true = ganzer Tag)
     */
    public function countWorkdaysForRequest(
        AbsenceRequest $request,
        DateTimeImmutable $rangeStart,
        DateTimeImmutable $rangeEndExclusive,
        array $holidayFractions,
    ): float {
        $start        = max($request->getStartDate(), $rangeStart);
        $endExclusive = min($request->getEndDate()->modify('+1 day'), $rangeEndExclusive);

        $sum = 0.0;
        for ($d = $start; $d < $endExclusive; $d = $d->modify('+1 day')) {
            if ((int) $d->format('N') >= 6) {
                continue;
            }
            $holiday = (float) ($holidayFractions[$d->format('Y-m-d')] ?? 0.0);
            $sum    += DayPortion::chargeableFraction($holiday, $request->getDayFraction());
        }

        return $sum;
    }
}
