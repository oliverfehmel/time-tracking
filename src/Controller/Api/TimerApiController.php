<?php

namespace App\Controller\Api;

use App\Api\OpenApi\DataResponse;
use App\Api\OpenApi\ProblemResponse;
use App\Api\View\TimeEntryView;
use App\Api\View\TimerView;
use App\Repository\TimeEntryRepository;
use App\Service\TimeEntryService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/timer')]
#[OA\Tag(name: 'Timer')]
#[ProblemResponse(401)]
final class TimerApiController extends AbstractApiController
{
    public function __construct(private readonly TimeEntryService $service) {}

    #[Route('', name: 'api_v1_timer_get', methods: ['GET'])]
    #[OA\Get(summary: 'Your running timer, if any')]
    #[DataResponse(TimerView::class)]
    public function show(TimeEntryRepository $repo): JsonResponse
    {
        return $this->item(TimerView::from($repo->findRunningForUser($this->currentUser())));
    }

    #[Route('/start', name: 'api_v1_timer_start', methods: ['POST'])]
    #[OA\Post(summary: 'Start your timer now')]
    #[DataResponse(TimeEntryView::class, status: 201, description: 'Started')]
    #[ProblemResponse(403, 'Read-only token')]
    #[ProblemResponse(409, 'A timer is already running')]
    public function start(): JsonResponse
    {
        return $this->item(TimeEntryView::from($this->service->start($this->currentUser())), Response::HTTP_CREATED);
    }

    #[Route('/stop', name: 'api_v1_timer_stop', methods: ['POST'])]
    #[OA\Post(summary: 'Stop your running timer now')]
    #[DataResponse(TimeEntryView::class, description: 'Stopped')]
    #[ProblemResponse(403, 'Read-only token')]
    #[ProblemResponse(409, 'No timer is running')]
    public function stop(): JsonResponse
    {
        return $this->item(TimeEntryView::from($this->service->stop($this->currentUser())));
    }
}
