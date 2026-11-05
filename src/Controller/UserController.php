<?php

namespace App\Controller;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Form\UserCreateType;
use App\Form\UserEditType;
use App\Repository\ApiTokenRepository;
use App\Repository\UserRepository;
use App\Security\ApiTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/users', name: '_admin_users_')]
final class UserController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(UserRepository $repo): Response
    {
        $users = $repo->findBy([], ['name' => 'ASC']);

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = new User();

        $form = $this->createForm(UserCreateType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = (string) $form->get('plainPassword')->getData();

            $user->setPassword($hasher->hashPassword($user, $plainPassword));

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'flash.user_created');

            return $this->redirectToRoute('_admin_users_index');
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        ApiTokenRepository $tokenRepo,
    ): Response {
        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            if (is_string($plainPassword) && trim($plainPassword) !== '') {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
            }

            $em->flush();

            $this->addFlash('success', 'flash.user_saved');

            return $this->redirectToRoute('_admin_users_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user'      => $user,
            'form'      => $form,
            'apiTokens' => $tokenRepo->findForUser($user),
        ]);
    }

    /**
     * Admins can revoke any user's API token (but not create tokens for others).
     */
    #[Route('/api-tokens/{id<\d+>}/revoke', name: 'api_token_revoke', methods: ['POST'])]
    public function revokeApiToken(ApiToken $token, Request $request, ApiTokenManager $tokenManager): Response
    {
        $userId = $token->getUser()->getId();

        if (!$this->isCsrfTokenValid('revoke_api_token_' . $token->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_admin_users_edit', ['id' => $userId]);
        }

        $tokenManager->revoke($token);
        $this->addFlash('success', 'api_token.revoked');

        return $this->redirectToRoute('_admin_users_edit', ['id' => $userId]);
    }

    #[Route('/{id<\d+>}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        User $user,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $token = (string) $request->request->get('_token');

        if (!$this->isCsrfTokenValid('delete_user_'.$user->getId(), $token)) {
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_admin_users_index');
        }

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', 'flash.user_deleted');

        return $this->redirectToRoute('_admin_users_index');
    }
}
