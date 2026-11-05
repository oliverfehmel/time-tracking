<?php

namespace App\Controller\Api;

use App\Api\OpenApi\DataResponse;
use App\Api\OpenApi\ProblemResponse;
use App\Api\View\TimeReportView;
use App\Repository\SettingsRepository;
use App\Service\UserYearReportBuilder;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Time report')]
#[ProblemResponse(400)]
#[ProblemResponse(401)]
final class TimeReportApiController extends AbstractApiController
{
    #[Route('/api/v1/users/{id<\d+>}/time-report/{year<19[7-9]\d|2\d{3}>}', name: 'api_v1_time_report', methods: ['GET'])]
    #[OA\Get(
        summary: 'Year report of a user',
        description: 'Per month and day: net worked time (after break deductions), target time and balance, holiday and absence '
            . 'fractions (half days = 0.5) and the work location. Target time is capped to today.',
    )]
    #[DataResponse(TimeReportView::class)]
    #[ProblemResponse(404)]
    public function report(int $id, int $year, UserYearReportBuilder $builder, SettingsRepository $settingsRepo): JsonResponse
    {
        $user = $this->findUser($id);

        return $this->item(TimeReportView::from((int) $user->getId(), $year, $builder->build($user, $year, $settingsRepo->getOrCreate())));
    }
}
