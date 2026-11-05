<?php

namespace App\Controller\Api;

use App\Api\ApiDates;
use App\Api\ApiPayload;
use App\Api\Dto\HolidayInput;
use App\Api\Dto\HolidayQuery;
use App\Api\OpenApi\DataResponse;
use App\Api\OpenApi\ProblemResponse;
use App\Api\View\HolidayView;
use App\Entity\Holiday;
use App\Repository\HolidayRepository;
use App\Repository\SettingsRepository;
use App\Repository\UserRepository;
use App\Service\DomainRuleViolation;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use SortDirection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/holidays')]
#[OA\Tag(name: 'Holidays')]
#[ProblemResponse(401)]
final class HolidayApiController extends AbstractApiController
{
    public function __construct(
        private readonly HolidayRepository $repo,
        private readonly UserRepository $userRepo,
        private readonly SettingsRepository $settingsRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'api_v1_holidays_list', methods: ['GET'])]
    #[OA\Get(summary: 'List holidays', description: 'Regular users get the holidays assigned to them (without user list), admins all holidays. Oldest first.')]
    #[DataResponse(HolidayView::class, collection: true)]
    #[ProblemResponse(403, 'userId of another user (regular users)')]
    #[ProblemResponse(404)]
    #[ProblemResponse(422)]
    public function list(#[MapQueryString] HolidayQuery $query = new HolidayQuery()): JsonResponse
    {
        $qb = $this->repo->createQueryBuilder('h')
            ->orderBy('h.date', SortDirection::Ascending)
            ->addOrderBy('h.id', SortDirection::Ascending);

        if ($query->userId !== null || !$this->isAdmin()) {
            $qb->andWhere(':u MEMBER OF h.users')->setParameter('u', $this->resolveUser($query->userId));
        }
        if ($query->from !== null) {
            $qb->andWhere('h.date >= :from')->setParameter('from', ApiDates::parseDate($query->from));
        }
        if ($query->to !== null) {
            $qb->andWhere('h.date <= :to')->setParameter('to', ApiDates::parseDate($query->to));
        }

        return $this->paginate($qb, $query, $this->view(...));
    }

    #[Route('/{id<\d+>}', name: 'api_v1_holidays_get', methods: ['GET'])]
    #[OA\Get(summary: 'Get a holiday')]
    #[DataResponse(HolidayView::class)]
    #[ProblemResponse(404)]
    public function show(int $id): JsonResponse
    {
        return $this->item($this->view($this->findHoliday($id)));
    }

    #[Route('', name: 'api_v1_holidays_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(summary: 'Create a holiday (admin)')]
    #[DataResponse(HolidayView::class, status: 201, description: 'Created')]
    #[ProblemResponse(403)]
    #[ProblemResponse(409, 'A holiday with this name already exists on this date')]
    #[ProblemResponse(415)]
    #[ProblemResponse(422)]
    public function create(#[MapRequestPayload] HolidayInput $input): JsonResponse
    {
        $holiday = new Holiday();
        $this->apply($holiday, $input);
        $this->em->persist($holiday);
        $this->em->flush();

        return $this->item($this->view($holiday), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'api_v1_holidays_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Patch(summary: 'Update a holiday (admin)', description: 'Merge patch; userIds replaces the assignment.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: HolidayInput::class)))]
    #[DataResponse(HolidayView::class)]
    #[ProblemResponse(403)]
    #[ProblemResponse(404)]
    #[ProblemResponse(409, 'A holiday with this name already exists on this date')]
    #[ProblemResponse(415)]
    #[ProblemResponse(422)]
    public function update(int $id, Request $request, ApiPayload $payload): JsonResponse
    {
        $holiday = $this->findHoliday($id);
        [$input] = $payload->merge($request, HolidayInput::fromHoliday($holiday));

        $this->apply($holiday, $input);
        $this->em->flush();

        return $this->item($this->view($holiday));
    }

    #[Route('/{id<\d+>}', name: 'api_v1_holidays_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Delete(summary: 'Delete a holiday (admin)')]
    #[OA\Response(response: 204, description: 'Deleted')]
    #[ProblemResponse(403)]
    #[ProblemResponse(404)]
    public function delete(int $id): Response
    {
        $this->em->remove($this->findHoliday($id));
        $this->em->flush();

        return $this->noContent();
    }

    private function apply(Holiday $holiday, HolidayInput $input): void
    {
        // Same rule as the form: half days can only be switched on while the feature is enabled.
        if ($input->halfDay && !$holiday->isHalfDay() && !$this->settingsRepo->getOrCreate()->isHalfDaysEnabled()) {
            throw new DomainRuleViolation('half_days_disabled', 'absence.request.half_days_disabled', propertyPath: 'halfDay');
        }

        $userIds = array_values(array_unique($input->userIds));
        $users   = $userIds === [] ? [] : $this->userRepo->findBy(['id' => $userIds]);
        if (count($users) !== count($userIds)) {
            throw new DomainRuleViolation('unknown_user', 'api.problem.unknown_user', propertyPath: 'userIds');
        }

        $holiday->setName(trim($input->name));
        $holiday->setDate(ApiDates::parseDate($input->date));
        $holiday->setHalfDay($input->halfDay);

        foreach ($holiday->getUsers()->toArray() as $user) {
            if (!in_array($user, $users, true)) {
                $holiday->removeUser($user);
            }
        }
        foreach ($users as $user) {
            $holiday->addUser($user);
        }
    }

    private function findHoliday(int $id): Holiday
    {
        return $this->ensureAccess($this->repo->find($id));
    }

    private function view(Holiday $holiday): HolidayView
    {
        return HolidayView::from($holiday, $this->isAdmin());
    }
}
