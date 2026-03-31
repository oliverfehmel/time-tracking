<?php

namespace App\Service;

use App\Entity\AbsenceRequest;
use App\Entity\User;
use App\Repository\AbsenceQuotaRepository;
use App\Repository\AbsenceRequestRepository;
use App\Repository\AbsenceTypeRepository;

final class AbsenceQuotaService
{
    public function __construct(
        private readonly AbsenceTypeRepository $typeRepo,
        private readonly AbsenceQuotaRepository $quotaRepo,
        private readonly AbsenceRequestRepository $requestRepo,
        private readonly AbsenceDayCalculator $dayCalc,
    ) {}

    /**
     * Liefert pro Typ: quota (int|null), usedApproved, usedPending, remaining (int|null)
     */
    public function getQuotaOverview(User $user, int $year, ?string $holidayRegion = null): array
    {
        $types = $this->typeRepo->findActive();
        $requests = $this->requestRepo->findForUserInYear($user, $year);

        $usedApproved = [];
        $usedPending = [];

        foreach ($requests as $r) {
            $days = $this->dayCalc->countChargeableDays(
                $r->getStartDate(),
                $r->getEndDate(),
                $holidayRegion,
                $user,
            );

            $typeId = $r->getType()->getId();
            if (!$typeId) {
                continue;
            }

            if ($r->getStatus() === AbsenceRequest::STATUS_APPROVED) {
                $usedApproved[$typeId] = ($usedApproved[$typeId] ?? 0) + $days;
            } elseif ($r->getStatus() === AbsenceRequest::STATUS_PENDING) {
                $usedPending[$typeId] = ($usedPending[$typeId] ?? 0) + $days;
            }
        }

        $result = [];
        foreach ($types as $type) {
            $quotaDays = null;

            if ($type->requiresQuota()) {
                $quota = $this->quotaRepo->findOneFor($user, $type, $year);
                $quotaDays = $quota?->getQuotaDays() ?? $type->getDefaultYearlyQuotaDays();
            }

            $typeId = $type->getId();
            $approved = $typeId ? ($usedApproved[$typeId] ?? 0) : 0;
            $pending = $typeId ? ($usedPending[$typeId] ?? 0) : 0;

            $remaining = null;
            if ($quotaDays !== null) {
                $remaining = max(0, $quotaDays - $approved);
            }

            $result[] = [
                'type' => $type,
                'quotaDays' => $quotaDays,
                'usedApprovedDays' => $approved,
                'usedPendingDays' => $pending,
                'remainingDays' => $remaining,
            ];
        }

        return $result;
    }
}
