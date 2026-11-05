<?php

namespace App\Api\View;

use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Quota usage of one user in one year, per active absence type')]
final readonly class QuotaOverviewView
{
    /**
     * @param list<QuotaItemView> $types
     */
    public function __construct(
        public int $userId,
        public int $year,
        public array $types,
    ) {}

    /**
     * @param list<array<string,mixed>> $overview rows of {@see \App\Service\AbsenceQuotaService::getQuotaOverview()}
     */
    public static function from(int $userId, int $year, array $overview): self
    {
        return new self($userId, $year, array_values(array_map(
            static fn(array $row) => new QuotaItemView(
                type: AbsenceTypeRefView::from($row['type']),
                quotaDays: $row['quotaDays'],
                allowOverLimit: $row['allowOverLimit'],
                usedApprovedDays: (float) $row['usedApprovedDays'],
                usedPendingDays: (float) $row['usedPendingDays'],
                remainingDays: $row['remainingDays'],
            ),
            $overview,
        )));
    }
}
