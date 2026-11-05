<?php

namespace App\Service;

use App\Repository\SettingsRepository;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Edit locks for time entries and work locations of regular users:
 * the global setting "users may change time entries" and the month lock (only the current and the previous month).
 */
final readonly class TimeEditPolicy
{
    public function __construct(
        private SettingsRepository $settingsRepo,
        private TimeTrackingCalculator $calc,
        private ClockInterface $clock,
    ) {}

    public function isEditingAllowed(): bool
    {
        return $this->settingsRepo->getOrCreate()->isUsersAreAllowedToChangeTimeEntries();
    }

    public function isMonthEditable(DateTimeImmutable $day): bool
    {
        return $this->calc->isEditableMonth($day, DateTimeImmutable::createFromInterface($this->clock->now()));
    }

    /**
     * @throws DomainRuleViolation when editing is disabled or one of the days lies in a locked month
     */
    public function assertCanEdit(DateTimeImmutable ...$days): void
    {
        if (!$this->isEditingAllowed()) {
            throw new DomainRuleViolation(
                'time_editing_disabled',
                'settings.users_not_allowed_to_change_time_entries',
                status: DomainRuleViolation::FORBIDDEN,
            );
        }

        foreach ($days as $day) {
            if (!$this->isMonthEditable($day)) {
                throw new DomainRuleViolation('month_not_editable', 'flash.month_not_editable', status: DomainRuleViolation::FORBIDDEN);
            }
        }
    }
}
