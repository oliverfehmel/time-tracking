<?php

namespace App\Service;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceQuota;
use App\Entity\AbsenceType;
use App\Entity\User;
use App\Repository\AbsenceQuotaRepository;
use App\Repository\AbsenceRequestRepository;
use App\Repository\AbsenceTypeRepository;
use DateTimeImmutable;

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
            $quota = null;
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
                'allowOverLimit' => $this->resolveAllowOverLimit($type, $quota),
                'allowOverLimitOverride' => $quota?->isAllowOverLimit(),
                'usedApprovedDays' => $approved,
                'usedPendingDays' => $pending,
                'remainingDays' => $remaining,
            ];
        }

        return $result;
    }

    public function validateRequestWithinQuota(
        User $user,
        AbsenceType $type,
        int $year,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?string $holidayRegion = null,
    ): ?string {
        if (!$type->requiresQuota()) {
            return null;
        }

        $quota = $this->quotaRepo->findOneFor($user, $type, $year);
        if ($this->resolveAllowOverLimit($type, $quota)) {
            return null;
        }

        $quotaDays = $quota?->getQuotaDays() ?? $type->getDefaultYearlyQuotaDays();
        if ($quotaDays === null) {
            return null;
        }

        $alreadyRequested = 0;
        foreach ($this->requestRepo->findForUserInYear($user, $year) as $request) {
            if ($request->getType()->getId() !== $type->getId()) {
                continue;
            }

            if (!in_array($request->getStatus(), [AbsenceRequest::STATUS_APPROVED, AbsenceRequest::STATUS_PENDING], true)) {
                continue;
            }

            $alreadyRequested += $this->dayCalc->countChargeableDays(
                $request->getStartDate(),
                $request->getEndDate(),
                $holidayRegion,
                $user,
            );
        }

        $requestedDays = $this->dayCalc->countChargeableDays($start, $end, $holidayRegion, $user);
        if ($alreadyRequested + $requestedDays <= $quotaDays) {
            return null;
        }

        return sprintf(
            'Das Kontingent reicht nicht aus: %d von %d Tagen sind bereits genehmigt oder offen, der neue Antrag umfasst %d Tage.',
            $alreadyRequested,
            $quotaDays,
            $requestedDays,
        );
    }

    private function resolveAllowOverLimit(AbsenceType $type, ?AbsenceQuota $quota): bool
    {
        $quotaSetting = $quota?->isAllowOverLimit();

        return $quotaSetting ?? $type->isAllowOverLimit();
    }
}
