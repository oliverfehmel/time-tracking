<?php

namespace App\Service;

use App\Entity\AbsenceRequest;
use App\Repository\AbsenceRequestRepository;
use App\Repository\HolidayRepository;

/**
 * Fachliche Prüfung eines neuen Abwesenheitsantrags (Datumslogik, halbe Tage, Überschneidungen).
 * Gibt den Übersetzungsschlüssel der Fehlermeldung zurück oder null, wenn alles passt.
 */
final readonly class AbsenceRequestValidator
{
    public function __construct(
        private AbsenceRequestRepository $requestRepo,
        private HolidayRepository $holidayRepo,
    ) {}

    public function validateDates(AbsenceRequest $absence, int $year): ?string
    {
        $user  = $absence->getRequestedBy();
        $start = $absence->getStartDate();
        $end   = $absence->getEndDate();

        if ($end < $start) {
            return 'absence.request.end_before_start';
        }

        if ((int) $start->format('Y') !== $year && (int) $end->format('Y') !== $year) {
            return 'absence.request.wrong_year';
        }

        if ($absence->isHalfDay() && $start->format('Y-m-d') !== $end->format('Y-m-d')) {
            return 'absence.request.half_day_single_day';
        }

        $overlapping = $this->requestRepo->findOverlappingApprovedOrPending($user, $start, $end);
        if ($overlapping === []) {
            return null;
        }

        // Überlappung ist nur für halbe Tage erlaubt (dann ist der Antrag ein Einzeltag) –
        // ein halber Feiertag an diesem Tag zählt in die Summe mit hinein.
        $holidayFraction = 0.0;
        if ($absence->isHalfDay()) {
            $holidays        = DayPortion::holidayMap($this->holidayRepo->findForUserBetween($user, $start, $start));
            $holidayFraction = $holidays[$start->format('Y-m-d')] ?? 0.0;
        }

        if (!DayPortion::fitsAlongside($absence->getDayFraction(), $overlapping, $holidayFraction)) {
            return 'absence.request.overlaps_existing';
        }

        return null;
    }
}
