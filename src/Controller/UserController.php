<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserCreateType;
use App\Form\UserEditType;
use App\Repository\UserRepository;
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

            $this->addFlash('success', 'User wurde angelegt.');

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
        UserPasswordHasherInterface $hasher
    ): Response {
        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            if (is_string($plainPassword) && trim($plainPassword) !== '') {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
            }

            $em->flush();

            $this->addFlash('success', 'User wurde gespeichert.');

            return $this->redirectToRoute('_admin_users_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        User $user,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $token = (string) $request->request->get('_token');

        if (!$this->isCsrfTokenValid('delete_user_'.$user->getId(), $token)) {
            $this->addFlash('error', 'Ungültiges CSRF-Token.');
            return $this->redirectToRoute('_admin_users_index');
        }

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', 'User wurde gelöscht.');

        return $this->redirectToRoute('_admin_users_index');
    }
}
