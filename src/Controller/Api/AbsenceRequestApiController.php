<?php

namespace App\Controller\Api;

use App\Api\ApiDates;
use App\Api\Dto\AbsenceRequestInput;
use App\Api\Dto\AbsenceRequestQuery;
use App\Api\Dto\RejectInput;
use App\Api\OpenApi\DataResponse;
use App\Api\OpenApi\ProblemResponse;
use App\Api\View\AbsenceRequestView;
use App\Entity\AbsenceRequest;
use App\Repository\AbsenceRequestRepository;
use App\Repository\AbsenceTypeRepository;
use App\Service\AbsenceDayCalculator;
use App\Service\AbsenceRequestService;
use App\Service\DomainRuleViolation;
use OpenApi\Attributes as OA;
use SortDirection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/absence-requests')]
#[OA\Tag(name: 'Absence requests')]
#[ProblemResponse(400)]
#[ProblemResponse(401)]
final class AbsenceRequestApiController extends AbstractApiController
{
    private const array META = ['notificationSent' => 'Whether the notification mail could be sent'];

    public function __construct(
        private readonly AbsenceRequestRepository $repo,
        private readonly AbsenceRequestService $service,
        private readonly AbsenceDayCalculator $dayCalc,
    ) {}

    #[Route('', name: 'api_v1_absence_requests_list', methods: ['GET'])]
    #[OA\Get(summary: 'List absence requests', description: 'Regular users get their own requests, admins all requests (optionally filtered by userId). Newest start date first.')]
    #[DataResponse(AbsenceRequestView::class, collection: true)]
    #[ProblemResponse(403, 'userId of another user (regular users)')]
    #[ProblemResponse(404)]
    #[ProblemResponse(422)]
    public function list(#[MapQueryString] AbsenceRequestQuery $query = new AbsenceRequestQuery()): JsonResponse
    {
        $qb = $this->repo->createQueryBuilder('a')
            ->addSelect('t')->join('a.type', 't')
            ->addSelect('u')->join('a.requestedBy', 'u')
            ->orderBy('a.startDate', SortDirection::Descending)
            ->addOrderBy('a.id', SortDirection::Descending);

        if ($query->userId !== null || !$this->isAdmin()) {
            $qb->andWhere('a.requestedBy = :u')->setParameter('u', $this->resolveUser($query->userId));
        }
        if ($query->year !== null) {
            $qb->andWhere('a.startDate <= :yearEnd')->setParameter('yearEnd', ApiDates::parseDate(sprintf('%04d-12-31', $query->year)))
                ->andWhere('a.endDate >= :yearStart')->setParameter('yearStart', ApiDates::parseDate(sprintf('%04d-01-01', $query->year)));
        }
        if ($query->status !== null) {
            $qb->andWhere('a.status = :status')->setParameter('status', $query->status);
        }

        return $this->paginate($qb, $query, $this->view(...));
    }

    #[Route('/{id<\d+>}', name: 'api_v1_absence_requests_get', methods: ['GET'])]
    #[OA\Get(summary: 'Get an absence request')]
    #[DataResponse(AbsenceRequestView::class)]
    #[ProblemResponse(404)]
    public function show(int $id): JsonResponse
    {
        return $this->item($this->view($this->findRequest($id)));
    }

    #[Route('', name: 'api_v1_absence_requests_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Request an absence',
        description: 'Same rules as in the app: end not before start; half days only for a single day and only while half days are enabled; '
            . 'no overlap with pending/approved requests (except two half days on a day without half-day holiday); quota. '
            . 'Types without approval are approved immediately. All admins are notified by mail.',
    )]
    #[DataResponse(AbsenceRequestView::class, status: 201, description: 'Created', meta: self::META)]
    #[ProblemResponse(403, 'userId of another user (regular users) or read-only token')]
    #[ProblemResponse(404, 'Unknown user')]
    #[ProblemResponse(415)]
    #[ProblemResponse(422)]
    public function create(#[MapRequestPayload(acceptFormat: 'json')] AbsenceRequestInput $input, AbsenceTypeRepository $typeRepo): JsonResponse
    {
        $type = $typeRepo->find((int) $input->typeId);
        if ($type === null) {
            throw new DomainRuleViolation('unknown_type', 'api.problem.unknown_type', propertyPath: 'typeId');
        }

        $absence = new AbsenceRequest();
        $absence->setRequestedBy($this->resolveUser($input->userId));
        $absence->setType($type);
        $absence->setStartDate(ApiDates::parseDate($input->startDate));
        $absence->setEndDate(ApiDates::parseDate($input->endDate ?? $input->startDate));
        $absence->setHalfDay($input->halfDay);
        $absence->setComment($input->comment !== null && trim($input->comment) !== '' ? trim($input->comment) : null);

        $notificationSent = $this->service->create($absence);

        return $this->item($this->view($absence), Response::HTTP_CREATED, ['notificationSent' => $notificationSent]);
    }

    #[Route('/{id<\d+>}/cancel', name: 'api_v1_absence_requests_cancel', methods: ['POST'])]
    #[OA\Post(summary: 'Cancel a pending or approved request')]
    #[DataResponse(AbsenceRequestView::class)]
    #[ProblemResponse(403, 'Read-only token')]
    #[ProblemResponse(404)]
    #[ProblemResponse(409, 'The request is rejected or already cancelled')]
    public function cancel(int $id): JsonResponse
    {
        $absence = $this->findRequest($id);
        $this->service->cancel($absence);

        return $this->item($this->view($absence));
    }

    #[Route('/{id<\d+>}/approve', name: 'api_v1_absence_requests_approve', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(summary: 'Approve a pending request (admin)', description: 'Admins cannot decide their own requests. The requester is notified by mail.')]
    #[DataResponse(AbsenceRequestView::class, meta: self::META)]
    #[ProblemResponse(403, 'Not an admin, read-only token or own request')]
    #[ProblemResponse(404)]
    #[ProblemResponse(409, 'The request is not pending')]
    public function approve(int $id): JsonResponse
    {
        $absence = $this->findRequest($id);
        $sent    = $this->service->approve($absence, $this->currentUser());

        return $this->item($this->view($absence), meta: ['notificationSent' => $sent]);
    }

    #[Route('/{id<\d+>}/reject', name: 'api_v1_absence_requests_reject', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(summary: 'Reject a pending request (admin)', description: 'A reason is required. Admins cannot decide their own requests. The requester is notified by mail.')]
    #[DataResponse(AbsenceRequestView::class, meta: self::META)]
    #[ProblemResponse(403, 'Not an admin, read-only token or own request')]
    #[ProblemResponse(404)]
    #[ProblemResponse(409, 'The request is not pending')]
    #[ProblemResponse(415)]
    #[ProblemResponse(422)]
    public function reject(int $id, #[MapRequestPayload(acceptFormat: 'json')] RejectInput $input): JsonResponse
    {
        $absence = $this->findRequest($id);
        $sent    = $this->service->reject($absence, $this->currentUser(), $input->reason);

        return $this->item($this->view($absence), meta: ['notificationSent' => $sent]);
    }

    private function findRequest(int $id): AbsenceRequest
    {
        return $this->ensureAccess($this->repo->find($id));
    }

    private function view(AbsenceRequest $absence): AbsenceRequestView
    {
        return AbsenceRequestView::from($absence, $this->dayCalc->countForRequest($absence));
    }
}
