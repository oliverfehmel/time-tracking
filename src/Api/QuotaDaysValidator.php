<?php

namespace App\Api;

use App\Form\AbsenceQuotaType;
use App\Repository\SettingsRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Applies the quota rules of the admin forms to API input: 0 to 366 days, 0.5 steps while half days are enabled,
 * whole days otherwise — a stored half value stays valid.
 */
final readonly class QuotaDaysValidator
{
    public function __construct(
        private SettingsRepository $settingsRepo,
        private ValidatorInterface $validator,
    ) {}

    /**
     * @throws ApiProblem with status 422
     */
    public function validate(?float $days, ?float $currentValue, string $propertyPath): void
    {
        if ($days === null) {
            return;
        }

        $options    = AbsenceQuotaType::quotaDaysOptions($this->settingsRepo->getOrCreate()->isHalfDaysEnabled(), '', '', $currentValue);
        $violations = $this->validator->validate($days, $options['constraints']);

        if (count($violations) > 0) {
            throw new ApiProblem(422, 'validation_failed', violations: [[
                'propertyPath' => $propertyPath,
                'message'      => (string) $violations[0]->getMessage(),
                'code'         => (string) ($violations[0]->getCode() ?? 'invalid'),
            ]]);
        }
    }
}
