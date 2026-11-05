<?php

namespace App\Service;

use App\Entity\AbsenceRequest;
use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Absence request workflow shared by the UI and the API: create (with all date, half-day, overlap and quota rules),
 * cancel, approve and reject — including the notification mails.
 */
final readonly class AbsenceRequestService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AbsenceRequestValidator $validator,
        private AbsenceQuotaService $quotaService,
        private AbsenceNotifier $notifier,
        private UserRepository $userRepo,
        private SettingsRepository $settingsRepo,
    ) {}

    /**
     * Validates and persists a new request (requester, type, dates, half-day flag and comment must be set).
     * Types without approval are approved immediately. All admins are notified.
     *
     * @param int|null $year the year the request is filed for (UI: year of the page); defaults to the start year
     * @return bool whether the admin notification mail could be sent
     * @throws DomainRuleViolation
     */
    public function create(AbsenceRequest $absence, ?int $year = null): bool
    {
        $year ??= (int) $absence->getStartDate()->format('Y');

        if (!$absence->getType()->isActive()) {
            throw new DomainRuleViolation('type_inactive', 'absence.request.type_inactive', propertyPath: 'typeId');
        }

        if ($absence->isHalfDay() && !$this->settingsRepo->getOrCreate()->isHalfDaysEnabled()) {
            throw new DomainRuleViolation('half_days_disabled', 'absence.request.half_days_disabled', propertyPath: 'halfDay');
        }

        if ($errorKey = $this->validator->validateDates($absence, $year)) {
            throw DomainRuleViolation::fromKey($errorKey, propertyPath: match ($errorKey) {
                'absence.request.half_day_single_day' => 'halfDay',
                'absence.request.end_before_start' => 'endDate',
                default => 'startDate',
            });
        }

        $shortfall = $this->quotaService->checkRequestWithinQuota(
            $absence->getRequestedBy(),
            $absence->getType(),
            $year,
            $absence->getStartDate(),
            $absence->getEndDate(),
            halfDay: $absence->isHalfDay(),
        );
        if ($shortfall !== null) {
            throw new DomainRuleViolation('quota_exceeded', 'absence.request.quota_exceeded', [
                'already'   => $shortfall->alreadyRequestedDays,
                'quota'     => $shortfall->quotaDays,
                'requested' => $shortfall->requestedDays,
            ], propertyPath: 'typeId');
        }

        if (!$absence->getType()->requiresApproval()) {
            $absence->setStatus(AbsenceRequest::STATUS_APPROVED);
        }

        $this->em->persist($absence);
        $this->em->flush();

        return $this->notifier->sendCreatedNotification($absence, $this->userRepo->findAdminEmails(), $year);
    }

    /**
     * @throws DomainRuleViolation if the request is neither pending nor approved
     */
    public function cancel(AbsenceRequest $absence): void
    {
        if (!in_array($absence->getStatus(), [AbsenceRequest::STATUS_PENDING, AbsenceRequest::STATUS_APPROVED], true)) {
            throw new DomainRuleViolation('cannot_cancel', 'absence.request.cannot_cancel', status: DomainRuleViolation::CONFLICT);
        }

        $absence->cancel();
        $this->em->flush();
    }

    /**
     * @throws DomainRuleViolation if the request is not pending or belongs to the deciding admin
     */
    public function assertDecidable(AbsenceRequest $absence, User $admin): void
    {
        if ($absence->getStatus() !== AbsenceRequest::STATUS_PENDING) {
            throw new DomainRuleViolation('not_pending', 'admin_absence.only_pending', status: DomainRuleViolation::CONFLICT);
        }

        if ($absence->getRequestedBy()->getId() === $admin->getId()) {
            throw new DomainRuleViolation('own_request', 'admin_absence.own_request_error', status: DomainRuleViolation::FORBIDDEN);
        }
    }

    /**
     * @return bool whether the decision mail could be sent
     * @throws DomainRuleViolation
     */
    public function approve(AbsenceRequest $absence, User $admin): bool
    {
        $this->assertDecidable($absence, $admin);

        $absence->approve($admin);
        $this->em->flush();

        return $this->notifier->sendDecision($absence, approved: true);
    }

    /**
     * @return bool whether the decision mail could be sent
     * @throws DomainRuleViolation
     */
    public function reject(AbsenceRequest $absence, User $admin, string $reason): bool
    {
        $this->assertDecidable($absence, $admin);

        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainRuleViolation('reason_required', 'admin_absence.reason_required', propertyPath: 'reason');
        }

        $absence->reject($admin, $reason);
        $this->em->flush();

        return $this->notifier->sendDecision($absence, approved: false);
    }
}
