<?php

namespace App\Api\View;

use App\Api\ApiDates;
use App\Entity\WorkLocation;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Explicitly recorded work location of a day')]
final readonly class WorkLocationView
{
    public function __construct(
        public int $userId,
        #[OA\Property(format: 'date')]
        public string $date,
        public WorkLocationTypeView $locationType,
    ) {}

    public static function from(WorkLocation $location): self
    {
        return new self(
            userId: (int) $location->getUser()->getId(),
            date: (string) ApiDates::date($location->getDate()),
            locationType: WorkLocationTypeView::from($location->getLocationType()),
        );
    }
}
