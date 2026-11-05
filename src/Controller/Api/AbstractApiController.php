<?php

namespace App\Controller\Api;

use App\Api\ApiProblem;
use App\Api\Dto\PageQuery;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Voter\DataAccessVoter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared helpers for the /api/v1 controllers: response envelope, pagination and the "own data vs. admin" rules
 * (see ADR 0001).
 */
abstract class AbstractApiController extends AbstractController
{
    public static function getSubscribedServices(): array
    {
        return parent::getSubscribedServices() + [UserRepository::class => UserRepository::class];
    }

    protected function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    protected function isAdmin(): bool
    {
        return $this->isGranted('ROLE_ADMIN');
    }

    /**
     * Admins write through the API without the edit locks that apply to regular users.
     */
    protected function enforceLocks(): bool
    {
        return !$this->isAdmin();
    }

    /**
     * Resolves a `userId` filter or field: null means the current user; another user is only allowed for admins.
     */
    protected function resolveUser(?int $userId): User
    {
        $current = $this->currentUser();
        if ($userId === null || $userId === $current->getId()) {
            return $current;
        }

        if (!$this->isAdmin()) {
            throw ApiProblem::forbidden('foreign_user');
        }

        return $this->findUser($userId);
    }

    /**
     * A user from the path: unknown or not accessible users are reported as 404.
     */
    protected function findUser(int $id): User
    {
        return $this->ensureAccess($this->container->get(UserRepository::class)->find($id));
    }

    /**
     * @template T of object
     * @param T|null $subject
     * @return T
     */
    protected function ensureAccess(?object $subject): object
    {
        if ($subject === null || !$this->isGranted(DataAccessVoter::ACCESS, $subject)) {
            throw ApiProblem::notFound();
        }

        return $subject;
    }

    /**
     * @param array<string,bool> $meta
     */
    protected function item(object|array $data, int $status = Response::HTTP_OK, array $meta = []): JsonResponse
    {
        $body = ['data' => $data];
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return $this->json($body, $status);
    }

    /**
     * @template T
     * @param callable(T): object $map
     */
    protected function paginate(QueryBuilder $qb, PageQuery $page, callable $map): JsonResponse
    {
        $qb->setFirstResult($page->offset())->setMaxResults($page->perPage);
        $paginator = new Paginator($qb, fetchJoinCollection: true);

        return $this->collection(array_map($map, iterator_to_array($paginator)), $page, count($paginator));
    }

    /**
     * Paginates an already loaded list.
     *
     * @param list<object> $items
     */
    protected function paginateList(array $items, PageQuery $page): JsonResponse
    {
        return $this->collection(array_slice($items, $page->offset(), $page->perPage), $page, count($items));
    }

    protected function noContent(): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param list<object> $items
     */
    private function collection(array $items, PageQuery $page, int $total): JsonResponse
    {
        return $this->json([
            'data' => array_values($items),
            'meta' => ['page' => $page->page, 'perPage' => $page->perPage, 'total' => $total],
        ]);
    }
}
