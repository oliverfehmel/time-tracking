<?php

namespace App\Api\Dto;

use App\Api\ApiDates;
use App\Entity\TimeEntry;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Body of POST /time-entries and PATCH /time-entries/{id}. Only finished entries; running entries come from the timer.
 */
final class TimeEntryInput
{
    #[Assert\NotBlank]
    #[Assert\Callback([self::class, 'validatePointInTime'])]
    #[OA\Property(description: 'RFC 3339 with offset (fractional seconds are ignored)', format: 'date-time', example: '2026-11-05T08:30:00+01:00')]
    public string $startedAt = '';

    #[Assert\NotBlank]
    #[Assert\Callback([self::class, 'validatePointInTime'])]
    #[OA\Property(description: 'RFC 3339 with offset, after startedAt', format: 'date-time', example: '2026-11-05T12:00:00+01:00')]
    public string $stoppedAt = '';

    #[OA\Property(description: 'Admins only, on create: owner of the entry (default: you). Ignored on update.')]
    public ?int $userId = null;

    public static function fromEntry(TimeEntry $entry): self
    {
        $input            = new self();
        $input->startedAt = (string) ApiDates::dateTime($entry->getStartedAt());
        $input->stoppedAt = (string) ApiDates::dateTime($entry->getStoppedAt());

        return $input;
    }

    public static function validatePointInTime(mixed $value, ExecutionContextInterface $context): void
    {
        if (is_string($value) && $value !== '' && ApiDates::tryParseDateTime($value) === null) {
            $constraint = new Assert\DateTime();
            $context->buildViolation($constraint->message)
                ->setCode(Assert\DateTime::INVALID_FORMAT_ERROR)
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateOrder(ExecutionContextInterface $context): void
    {
        $start = ApiDates::tryParseDateTime($this->startedAt);
        $stop  = ApiDates::tryParseDateTime($this->stoppedAt);

        if ($start && $stop && $stop <= $start) {
            $context->buildViolation('api.stopped_before_started')
                ->atPath('stoppedAt')
                ->setCode('stopped_before_started')
                ->addViolation();
        }
    }
}
