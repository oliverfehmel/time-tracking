<?php

namespace App\Controller;

use App\Entity\TimeEntry;
use App\Entity\User;
use App\Repository\TimeEntryRepository;
use App\Service\DashboardDataBuilder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardDataBuilder $dashboardBuilder,
    ) {}

    #[Route('/', name: '_home', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('dashboard/index.html.twig', $this->dashboardBuilder->build($user));
    }

    #[Route('/time/start', name: '_time_start', methods: ['POST'])]
    public function start(
        #[CurrentUser] User $user,
        Request $request,
        TimeEntryRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('time_start', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiges CSRF-Token.');
            return $this->redirectToRoute('_home');
        }

        if ($repo->findRunningForUser($user)) {
            $this->addFlash('error', 'Es läuft bereits ein Timer.');
            return $this->redirectToRoute('_home');
        }

        $entry = new TimeEntry();
        $entry->setUser($user);
        $entry->setStartedAt(new DateTimeImmutable());

        $em->persist($entry);
        $em->flush();

        $this->addFlash('success', 'Timer gestartet.');
        return $this->redirectToRoute('_home');
    }

    #[Route('/time/stop', name: '_time_stop', methods: ['POST'])]
    public function stop(
        #[CurrentUser] User $user,
        Request $request,
        TimeEntryRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('time_stop', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiges CSRF-Token.');
            return $this->redirectToRoute('_home');
        }

        $running = $repo->findRunningForUser($user);
        if (!$running) {
            $this->addFlash('error', 'Kein laufender Timer gefunden.');
            return $this->redirectToRoute('_home');
        }

        $running->setStoppedAt(new DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Timer gestoppt.');
        return $this->redirectToRoute('_home');
    }
}
