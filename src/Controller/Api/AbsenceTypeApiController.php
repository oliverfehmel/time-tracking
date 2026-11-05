<?php

namespace App\Controller\Api;

use App\Api\ApiPayload;
use App\Api\ApiProblem;
use App\Api\Dto\AbsenceTypeInput;
use App\Api\Dto\PageQuery;
use App\Api\OpenApi\DataResponse;
use App\Api\OpenApi\ProblemResponse;
use App\Api\QuotaDaysValidator;
use App\Api\View\AbsenceTypeView;
use App\Entity\AbsenceType;
use App\Repository\AbsenceTypeRepository;
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

#[Route('/api/v1/absence-types')]
#[OA\Tag(name: 'Absence types')]
#[ProblemResponse(401)]
final class AbsenceTypeApiController extends AbstractApiController
{
    public function __construct(
        private readonly AbsenceTypeRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly QuotaDaysValidator $quotaValidator,
    ) {}

    #[Route('', name: 'api_v1_absence_types_list', methods: ['GET'])]
    #[OA\Get(summary: 'List absence types', description: 'Regular users get the active types, admins all types.')]
    #[DataResponse(AbsenceTypeView::class, collection: true)]
    #[ProblemResponse(422)]
    public function list(#[MapQueryString] PageQuery $page = new PageQuery()): JsonResponse
    {
        $qb = $this->repo->createQueryBuilder('t')->orderBy('t.name', SortDirection::Ascending);
        if (!$this->isAdmin()) {
            $qb->andWhere('t.isActive = true');
        }

        return $this->paginate($qb, $page, AbsenceTypeView::from(...));
    }

    #[Route('/{id<\d+>}', name: 'api_v1_absence_types_get', methods: ['GET'])]
    #[OA\Get(summary: 'Get an absence type')]
    #[DataResponse(AbsenceTypeView::class)]
    #[ProblemResponse(404)]
    public function show(int $id): JsonResponse
    {
        return $this->item(AbsenceTypeView::from($this->findType($id)));
    }

    #[Route('', name: 'api_v1_absence_types_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(summary: 'Create an absence type (admin)')]
    #[DataResponse(AbsenceTypeView::class, status: 201, description: 'Created')]
    #[ProblemResponse(403)]
    #[ProblemResponse(409, 'Name or key already in use')]
    #[ProblemResponse(415)]
    #[ProblemResponse(422)]
    public function create(#[MapRequestPayload] AbsenceTypeInput $input): JsonResponse
    {
        $this->quotaValidator->validate($input->defaultYearlyQuotaDays, null, 'defaultYearlyQuotaDays');

        $type = new AbsenceType();
        $this->apply($type, $input);
        $this->em->persist($type);
        $this->em->flush();

        return $this->item(AbsenceTypeView::from($type), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'api_v1_absence_types_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Patch(summary: 'Update an absence type (admin)', description: 'Merge patch. Types cannot be deleted; set active to false instead.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: AbsenceTypeInput::class)))]
    #[DataResponse(AbsenceTypeView::class)]
    #[ProblemResponse(403)]
    #[ProblemResponse(404)]
    #[ProblemResponse(409, 'Name or key already in use')]
    #[ProblemResponse(415)]
    #[ProblemResponse(422)]
    public function update(int $id, Request $request, ApiPayload $payload): JsonResponse
    {
        $type = $this->findType($id);
        [$input] = $payload->merge($request, AbsenceTypeInput::fromType($type));
        $this->quotaValidator->validate($input->defaultYearlyQuotaDays, $type->getDefaultYearlyQuotaDays(), 'defaultYearlyQuotaDays');

        $this->apply($type, $input);
        $this->em->flush();

        return $this->item(AbsenceTypeView::from($type));
    }

    private function findType(int $id): AbsenceType
    {
        $type = $this->repo->find($id);
        if ($type === null || (!$type->isActive() && !$this->isAdmin())) {
            throw ApiProblem::notFound();
        }

        return $type;
    }

    private function apply(AbsenceType $type, AbsenceTypeInput $input): void
    {
        $type->setName(trim($input->name));
        $type->setKeyName($input->key);
        $type->setIsActive($input->active);
        $type->setRequiresApproval($input->requiresApproval);
        $type->setRequiresQuota($input->requiresQuota);
        $type->setDefaultYearlyQuotaDays($input->defaultYearlyQuotaDays);
        $type->setAllowOverLimit($input->allowOverLimit);
    }
}
