<?php

namespace App\Controller\Api;

use App\Api\ApiPayload;
use App\Api\ApiProblem;
use App\Api\Dto\PageQuery;
use App\Api\Dto\UserInput;
use App\Api\OpenApi\DataResponse;
use App\Api\OpenApi\ProblemResponse;
use App\Api\View\UserView;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use SortDirection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/users')]
#[OA\Tag(name: 'Users')]
#[ProblemResponse(401)]
final class UserApiController extends AbstractApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('', name: 'api_v1_users_list', methods: ['GET'])]
    #[OA\Get(summary: 'List users', description: 'Admins get all users, regular users only themselves.')]
    #[DataResponse(UserView::class, collection: true)]
    #[ProblemResponse(422)]
    public function list(UserRepository $repo, #[MapQueryString] PageQuery $page = new PageQuery()): JsonResponse
    {
        $qb = $repo->createQueryBuilder('u')->orderBy('u.name', SortDirection::Ascending)->addOrderBy('u.id', SortDirection::Ascending);
        if (!$this->isAdmin()) {
            $qb->andWhere('u = :me')->setParameter('me', $this->currentUser());
        }

        return $this->paginate($qb, $page, UserView::from(...));
    }

    #[Route('/{id<\d+>}', name: 'api_v1_users_get', methods: ['GET'])]
    #[OA\Get(summary: 'Get a user')]
    #[DataResponse(UserView::class)]
    #[ProblemResponse(404)]
    public function show(int $id): JsonResponse
    {
        return $this->item(UserView::from($this->findUser($id)));
    }

    #[Route('', name: 'api_v1_users_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(summary: 'Create a user (admin)')]
    #[DataResponse(UserView::class, status: 201, description: 'Created')]
    #[ProblemResponse(403)]
    #[ProblemResponse(409, 'Email address already in use')]
    #[ProblemResponse(415)]
    #[ProblemResponse(422, 'Validation failed, e.g. password missing')]
    public function create(#[MapRequestPayload] UserInput $input, ValidatorInterface $validator): JsonResponse
    {
        $missing = $validator->validate($input->password, new NotBlank());
        if (count($missing) > 0) {
            throw ApiProblem::validation('password', (string) $missing[0]->getMessage(), (string) $missing[0]->getCode());
        }

        $user = new User();
        $this->apply($user, $input);

        $this->em->persist($user);
        $this->em->flush();

        return $this->item(UserView::from($user), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'api_v1_users_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Patch(summary: 'Update a user (admin)', description: 'Merge patch: send only the fields to change. Admins cannot remove their own admin rights.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UserInput::class)))]
    #[DataResponse(UserView::class)]
    #[ProblemResponse(403)]
    #[ProblemResponse(404)]
    #[ProblemResponse(409, 'Email address already in use or admin would demote themselves')]
    #[ProblemResponse(415)]
    #[ProblemResponse(422)]
    public function update(int $id, Request $request, ApiPayload $payload): JsonResponse
    {
        $user = $this->findUser($id);
        [$input] = $payload->merge($request, UserInput::fromUser($user));

        if ($user->getId() === $this->currentUser()->getId() && !$input->admin) {
            throw new ApiProblem(409, 'cannot_demote_self');
        }

        $this->apply($user, $input);
        $this->em->flush();

        return $this->item(UserView::from($user));
    }

    #[Route('/{id<\d+>}', name: 'api_v1_users_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Delete(summary: 'Delete a user (admin)', description: 'Fails with 409 while the user still has time entries, absences or other data.')]
    #[OA\Response(response: 204, description: 'Deleted')]
    #[ProblemResponse(403)]
    #[ProblemResponse(404)]
    #[ProblemResponse(409)]
    public function delete(int $id): Response
    {
        $user = $this->findUser($id);
        if ($user->getId() === $this->currentUser()->getId()) {
            throw new ApiProblem(409, 'cannot_delete_self');
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->noContent();
    }

    private function apply(User $user, UserInput $input): void
    {
        $user->setEmail(trim($input->email));
        $user->setName(trim($input->name));
        $user->setDailyWorkMinutes($input->dailyWorkMinutes);
        $user->setRoles($input->admin ? ['ROLE_ADMIN'] : []);

        if ($input->password !== null && $input->password !== '') {
            $user->setPassword($this->hasher->hashPassword($user, $input->password));
        }
    }
}
