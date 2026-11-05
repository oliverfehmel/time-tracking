<?php

namespace App\Controller\Api;

use App\Api\ApiDates;
use App\Api\ApiPayload;
use App\Api\ApiProblem;
use App\Api\Dto\TimeEntryInput;
use App\Api\Dto\TimeEntryQuery;
use App\Api\OpenApi\DataResponse;
use App\Api\OpenApi\ProblemResponse;
use App\Api\View\TimeEntryView;
use App\Entity\TimeEntry;
use App\Repository\TimeEntryRepository;
use App\Service\TimeEditPolicy;
use App\Service\TimeEntryService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use SortDirection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/time-entries')]
#[OA\Tag(name: 'Time entries')]
#[ProblemResponse(401)]
final class TimeEntryApiController extends AbstractApiController
{
    private const array META = ['adjusted' => 'The time range was moved to the free part because it overlapped existing entries'];

    public function __construct(
        private readonly TimeEntryRepository $repo,
        private readonly TimeEntryService $service,
        private readonly TimeEditPolicy $editPolicy,
    ) {}

    #[Route('', name: 'api_v1_time_entries_list', methods: ['GET'])]
    #[OA\Get(summary: 'List time entries', description: 'Entries overlapping [from, to), oldest first. Regular users only see their own entries.')]
    #[DataResponse(TimeEntryView::class, collection: true)]
    #[ProblemResponse(403, 'userId of another user (regular users)')]
    #[ProblemResponse(404)]
    #[ProblemResponse(422)]
    public function list(#[MapQueryString] TimeEntryQuery $query = new TimeEntryQuery()): JsonResponse
    {
        $user = $this->resolveUser($query->userId);
        $qb   = $this->repo->createQueryBuilder('t')
            ->andWhere('t.user = :u')->setParameter('u', $user)
            ->orderBy('t.startedAt', SortDirection::Ascending)
            ->addOrderBy('t.id', SortDirection::Ascending);

        if ($query->from !== null) {
            $qb->andWhere('(t.stoppedAt IS NULL OR t.stoppedAt > :from)')->setParameter('from', ApiDates::parseDate($query->from));
        }
        if ($query->to !== null) {
            $qb->andWhere('t.startedAt < :to')->setParameter('to', ApiDates::parseDate($query->to));
        }

        return $this->paginate($qb, $query, TimeEntryView::from(...));
    }

    #[Route('/{id<\d+>}', name: 'api_v1_time_entries_get', methods: ['GET'])]
    #[OA\Get(summary: 'Get a time entry')]
    #[DataResponse(TimeEntryView::class)]
    #[ProblemResponse(404)]
    public function show(int $id): JsonResponse
    {
        return $this->item(TimeEntryView::from($this->findEntry($id)));
    }

    #[Route('', name: 'api_v1_time_entries_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a finished time entry',
        description: 'Overlaps with existing entries are cut away (meta.adjusted); a range that is completely covered is rejected. '
            . 'Regular users can only write the current and the previous month, and only while time-entry editing is allowed.',
    )]
    #[DataResponse(TimeEntryView::class, status: 201, description: 'Created', meta: self::META)]
    #[ProblemResponse(403)]
    #[ProblemResponse(404)]
    #[ProblemResponse(415)]
    #[ProblemResponse(422)]
    public function create(#[MapRequestPayload] TimeEntryInput $input): JsonResponse
    {
        $user = $this->resolveUser($input->userId);
        $this->editPolicy->isEditingAllowed(); // loads the settings row before the entity is touched

        $entry = new TimeEntry();
        $entry->setUser($user);
        $entry->setStartedAt(ApiDates::parseDateTime($input->startedAt));
        $entry->setStoppedAt(ApiDates::parseDateTime($input->stoppedAt));

        $adjusted = $this->service->create($entry, $this->enforceLocks());

        return $this->item(TimeEntryView::from($entry), Response::HTTP_CREATED, ['adjusted' => $adjusted]);
    }

    #[Route('/{id<\d+>}', name: 'api_v1_time_entries_update', methods: ['PATCH'])]
    #[OA\Patch(summary: 'Change a finished time entry', description: 'Merge patch. Same rules as on create; running entries can only be stopped via the timer.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: TimeEntryInput::class)))]
    #[DataResponse(TimeEntryView::class, meta: self::META)]
    #[ProblemResponse(403)]
    #[ProblemResponse(404)]
    #[ProblemResponse(409, 'The entry is a running timer')]
    #[ProblemResponse(415)]
    #[ProblemResponse(422)]
    public function update(int $id, Request $request, ApiPayload $payload): JsonResponse
    {
        $entry = $this->findEntry($id);
        if ($entry->isRunning()) {
            throw new ApiProblem(409, 'running_entry');
        }

        [$input] = $payload->merge($request, TimeEntryInput::fromEntry($entry));
        $this->editPolicy->isEditingAllowed();

        $originalStart = $entry->getStartedAt();
        $entry->setStartedAt(ApiDates::parseDateTime($input->startedAt));
        $entry->setStoppedAt(ApiDates::parseDateTime($input->stoppedAt));

        $adjusted = $this->service->update($entry, $originalStart, $this->enforceLocks());

        return $this->item(TimeEntryView::from($entry), meta: ['adjusted' => $adjusted]);
    }

    #[Route('/{id<\d+>}', name: 'api_v1_time_entries_delete', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Delete a time entry', description: 'Deleting a running entry discards the timer.')]
    #[OA\Response(response: 204, description: 'Deleted')]
    #[ProblemResponse(403)]
    #[ProblemResponse(404)]
    public function delete(int $id): Response
    {
        $this->service->delete($this->findEntry($id), $this->enforceLocks());

        return $this->noContent();
    }

    private function findEntry(int $id): TimeEntry
    {
        return $this->ensureAccess($this->repo->find($id));
    }
}
