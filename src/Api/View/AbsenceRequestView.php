<?php

namespace App\Api\View;

use App\Api\ApiDates;
use App\Entity\AbsenceRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'An absence request')]
final readonly class AbsenceRequestView
{
    public function __construct(
        public int $id,
        public int $userId,
        public AbsenceTypeRefView $type,
        #[OA\Property(format: 'date')]
        public string $startDate,
        #[OA\Property(format: 'date')]
        public string $endDate,
        #[OA\Property(description: 'Half-day request (always a single day)')]
        public bool $halfDay,
        #[OA\Property(description: 'Share of a workday per calendar day: 1.0 or 0.5')]
        public float $dayFraction,
        #[OA\Property(description: 'Chargeable days: workdays minus weekends and the user\'s holidays (half-day holidays count half), times dayFraction')]
        public float $days,
        #[OA\Property(enum: [AbsenceRequest::STATUS_PENDING, AbsenceRequest::STATUS_APPROVED, AbsenceRequest::STATUS_REJECTED, AbsenceRequest::STATUS_CANCELLED])]
        public string $status,
        public ?string $comment,
        #[OA\Property(format: 'date-time')]
        public string $createdAt,
        #[OA\Property(description: 'Admin who approved or rejected the request')]
        public ?int $decidedBy,
        #[OA\Property(format: 'date-time')]
        public ?string $decidedAt,
        public ?string $rejectReason,
    ) {}

    public static function from(AbsenceRequest $request, float $days): self
    {
        return new self(
            id: (int) $request->getId(),
            userId: (int) $request->getRequestedBy()->getId(),
            type: AbsenceTypeRefView::from($request->getType()),
            startDate: (string) ApiDates::date($request->getStartDate()),
            endDate: (string) ApiDates::date($request->getEndDate()),
            halfDay: $request->isHalfDay(),
            dayFraction: $request->getDayFraction(),
            days: $days,
            status: $request->getStatus(),
            comment: $request->getComment(),
            createdAt: (string) ApiDates::dateTime($request->getCreatedAt()),
            decidedBy: $request->getApprovedBy()?->getId(),
            decidedAt: ApiDates::dateTime($request->getApprovedAt()),
            rejectReason: $request->getRejectReason(),
        );
    }
}
