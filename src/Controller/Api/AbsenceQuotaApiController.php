<?php

namespace App\Controller\Api;

use App\Api\ApiProblem;
use App\Api\Dto\QuotaInput;
use App\Api\Dto\YearQuery;
use App\Api\OpenApi\DataResponse;
use App\Api\OpenApi\ProblemResponse;
use App\Api\QuotaDaysValidator;
use App\Api\View\AbsenceQuotaView;
use App\Api\View\QuotaOverviewView;
use App\Entity\AbsenceQuota;
use App\Entity\AbsenceType;
use App\Entity\User;
use App\Repository\AbsenceQuotaRepository;
use App\Repository\AbsenceTypeRepository;
use App\Service\AbsenceQuotaService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/users/{id<\d+>}/absence-quotas')]
#[OA\Tag(name: 'Absence quotas')]
#[ProblemResponse(400)]
#[ProblemResponse(401)]
final class AbsenceQuotaApiController extends AbstractApiController
{
    public function __construct(
        private readonly AbsenceQuotaRepository $quotaRepo,
        private readonly AbsenceTypeRepository $typeRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'api_v1_absence_quotas_overview', methods: ['GET'])]
    #[OA\Get(summary: 'Quota overview of a user', description: 'Per active absence type: quota, approved and pending days and the remaining days (half days count 0.5).')]
    #[DataResponse(QuotaOverviewView::class)]
    #[ProblemResponse(404)]
    #[ProblemResponse(422)]
    public function overview(int $id, AbsenceQuotaService $quotaService, ClockInterface $clock, #[MapQueryString] YearQuery $query = new YearQuery()): JsonResponse
    {
        $user = $this->findUser($id);
        $year = $query->year ?? (int) $clock->now()->format('Y');

        return $this->item(QuotaOverviewView::from((int) $user->getId(), $year, $quotaService->getQuotaOverview($user, $year)));
    }

    #[Route('/{year<19[7-9]\d|2\d{3}>}/{typeId<\d+>}', name: 'api_v1_absence_quotas_set', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Put(summary: 'Set the individual quota of a user (admin)', description: 'Overrides the type default for this user and year.')]
    #[DataResponse(AbsenceQuotaView::class)]
    #[ProblemResponse(403)]
    #[ProblemResponse(404)]
    #[ProblemResponse(415)]
    #[ProblemResponse(422)]
    public function set(int $id, int $year, int $typeId, #[MapRequestPayload(acceptFormat: 'json')] QuotaInput $input, QuotaDaysValidator $validator): JsonResponse
    {
        $user  = $this->findUser($id);
        $type  = $this->findType($typeId);
        $quota = $this->quotaRepo->findOneFor($user, $type, $year);

        $validator->validate($input->quotaDays, $quota?->getQuotaDays(), 'quotaDays');

        if ($quota === null) {
            $quota = new AbsenceQuota();
            $quota->setUser($user);
            $quota->setType($type);
            $quota->setYear($year);
            $this->em->persist($quota);
        }

        $quota->setQuotaDays($input->quotaDays);
        $quota->setAllowOverLimit($input->allowOverLimit);
        $this->em->flush();

        return $this->item(AbsenceQuotaView::from($quota));
    }

    #[Route('/{year<19[7-9]\d|2\d{3}>}/{typeId<\d+>}', name: 'api_v1_absence_quotas_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Delete(summary: 'Remove the individual quota of a user (admin)', description: 'The type default applies again.')]
    #[OA\Response(response: 204, description: 'Removed')]
    #[ProblemResponse(403)]
    #[ProblemResponse(404, 'Unknown user/type or no individual quota')]
    public function delete(int $id, int $year, int $typeId): Response
    {
        $quota = $this->quotaRepo->findOneFor($this->findUser($id), $this->findType($typeId), $year);
        if (!$quota instanceof AbsenceQuota) {
            throw ApiProblem::notFound();
        }

        $this->em->remove($quota);
        $this->em->flush();

        return $this->noContent();
    }

    private function findType(int $typeId): AbsenceType
    {
        return $this->typeRepo->find($typeId) ?? throw ApiProblem::notFound();
    }
}
