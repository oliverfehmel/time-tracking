<?php

namespace App\Controller\Api;

use App\Api\ApiDates;
use App\Api\ApiProblem;
use App\Api\Dto\DateRangeQuery;
use App\Api\Dto\PageQuery;
use App\Api\Dto\WorkLocationInput;
use App\Api\OpenApi\DataResponse;
use App\Api\OpenApi\ProblemResponse;
use App\Api\View\WorkLocationTypeView;
use App\Api\View\WorkLocationView;
use App\Repository\WorkLocationRepository;
use App\Repository\WorkLocationTypeRepository;
use App\Service\TimeEditPolicy;
use App\Service\WorkLocationService;
use OpenApi\Attributes as OA;
use SortDirection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

#[OA\Tag(name: 'Work locations')]
#[ProblemResponse(400)]
#[ProblemResponse(401)]
final class WorkLocationApiController extends AbstractApiController
{
    private const string DATE_PATTERN = '\d{4}-\d{2}-\d{2}';

    public function __construct(
        private readonly WorkLocationRepository $locationRepo,
        private readonly WorkLocationTypeRepository $typeRepo,
        private readonly WorkLocationService $service,
        private readonly TimeEditPolicy $editPolicy,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/api/v1/work-location-types', name: 'api_v1_work_location_types_list', methods: ['GET'])]
    #[OA\Get(summary: 'List work location types', description: 'Regular users get the active types, admins all types.')]
    #[DataResponse(WorkLocationTypeView::class, collection: true)]
    #[ProblemResponse(422)]
    public function types(#[MapQueryString] PageQuery $page = new PageQuery()): JsonResponse
    {
        $qb = $this->typeRepo->createQueryBuilder('t')->orderBy('t.name', SortDirection::Ascending);
        if (!$this->isAdmin()) {
            $qb->andWhere('t.isActive = true');
        }

        return $this->paginate($qb, $page, WorkLocationTypeView::from(...));
    }

    #[Route('/api/v1/users/{id<\d+>}/work-locations', name: 'api_v1_work_locations_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List explicitly recorded work locations of a user',
        description: 'Days without a record fall back to the default location type (only on workdays with time entries, see the time report).',
    )]
    #[DataResponse(WorkLocationView::class, collection: true)]
    #[ProblemResponse(404)]
    #[ProblemResponse(422)]
    public function list(int $id, #[MapQueryString] DateRangeQuery $query = new DateRangeQuery()): JsonResponse
    {
        $qb = $this->locationRepo->createQueryBuilder('l')
            ->addSelect('t')->join('l.locationType', 't')
            ->andWhere('l.user = :u')->setParameter('u', $this->findUser($id))
            ->orderBy('l.date', SortDirection::Ascending);

        if ($query->from !== null) {
            $qb->andWhere('l.date >= :from')->setParameter('from', ApiDates::parseDate($query->from));
        }
        if ($query->to !== null) {
            $qb->andWhere('l.date <= :to')->setParameter('to', ApiDates::parseDate($query->to));
        }

        return $this->paginate($qb, $query, WorkLocationView::from(...));
    }

    #[Route('/api/v1/users/{id<\d+>}/work-locations/{date}', name: 'api_v1_work_locations_set', requirements: ['date' => self::DATE_PATTERN], methods: ['PUT'])]
    #[OA\Put(summary: 'Set the work location of a day', description: 'Same edit locks as time entries for regular users.')]
    #[DataResponse(WorkLocationView::class)]
    #[ProblemResponse(403)]
    #[ProblemResponse(404)]
    #[ProblemResponse(415)]
    #[ProblemResponse(422)]
    public function set(int $id, string $date, #[MapRequestPayload(acceptFormat: 'json')] WorkLocationInput $input): JsonResponse
    {
        $user = $this->findUser($id);
        $this->editPolicy->isEditingAllowed();

        $location = $this->service->setForDay(
            $user,
            $this->parseDay($date),
            $this->typeRepo->find((int) $input->locationTypeId),
            $this->enforceLocks(),
        );

        return $this->item(WorkLocationView::from($location));
    }

    #[Route('/api/v1/users/{id<\d+>}/work-locations/{date}', name: 'api_v1_work_locations_clear', requirements: ['date' => self::DATE_PATTERN], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Remove the work location of a day', description: 'The day falls back to the default location type.')]
    #[OA\Response(response: 204, description: 'Removed')]
    #[ProblemResponse(403)]
    #[ProblemResponse(404, 'Unknown user or no work location recorded for this day')]
    #[ProblemResponse(422, 'Invalid date')]
    public function clear(int $id, string $date): Response
    {
        if (!$this->service->clearForDay($this->findUser($id), $this->parseDay($date), $this->enforceLocks())) {
            throw ApiProblem::notFound();
        }

        return $this->noContent();
    }

    private function parseDay(string $date): \DateTimeImmutable
    {
        // The route only checks the shape; impossible dates like 2026-02-30 are invalid input, not an unknown resource.
        $template = (new Assert\Date())->message;

        return ApiDates::tryParseDate($date)
            ?? throw ApiProblem::validation('date', $this->translator->trans($template, [], 'validators'), Assert\Date::INVALID_DATE_ERROR);
    }
}
