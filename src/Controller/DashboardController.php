<?php

namespace App\Controller;

use App\Entity\TimeEntry;
use App\Entity\User;
use App\Entity\WorkLocation;
use App\Repository\SettingsRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\WorkLocationRepository;
use App\Repository\WorkLocationTypeRepository;
use App\Service\DashboardDataBuilder;
use App\Service\TimeTrackingCalculator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

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
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_home');
        }

        if ($repo->findRunningForUser($user)) {
            $this->addFlash('error', 'flash.timer_already_running');
            return $this->redirectToRoute('_home');
        }

        $entry = new TimeEntry();
        $entry->setUser($user);
        $entry->setStartedAt(new DateTimeImmutable());

        $em->persist($entry);
        $em->flush();

        $this->addFlash('success', 'flash.timer_started');
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
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_home');
        }

        $running = $repo->findRunningForUser($user);
        if (!$running) {
            $this->addFlash('error', 'flash.no_running_timer');
            return $this->redirectToRoute('_home');
        }

        $running->setStoppedAt(new DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'flash.timer_stopped');
        return $this->redirectToRoute('_home');
    }

    #[Route('/time/work-location', name: '_time_location_dashboard', methods: ['POST'])]
    public function setTodayWorkLocation(
        #[CurrentUser] User $user,
        Request $request,
        WorkLocationRepository $locationRepo,
        WorkLocationTypeRepository $typeRepo,
        SettingsRepository $settingsRepo,
        TimeTrackingCalculator $calc,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        if (!$settingsRepo->getOrCreate()->isUsersAreAllowedToChangeTimeEntries()) {
            $this->addFlash('error', $translator->trans('settings.users_not_allowed_to_change_time_entries'));
            return $this->redirectToRoute('_home');
        }

        if (!$this->isCsrfTokenValid('set_work_location_today', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_home');
        }

        $today = new DateTimeImmutable('today');

        if (!$calc->isEditableMonth($today, new DateTimeImmutable())) {
            $this->addFlash('error', 'flash.month_not_editable');
            return $this->redirectToRoute('_home');
        }

        $typeId = (int) $request->request->get('location_type_id');
        $type   = $typeRepo->find($typeId);

        if (!$type || !$type->isActive()) {
            $this->addFlash('error', 'flash.work_location_invalid');
            return $this->redirectToRoute('_home');
        }

        $location = $locationRepo->findForUserOnDate($user, $today);

        if ($location === null) {
            $location = new WorkLocation();
            $location->setUser($user);
            $location->setDate($today);
            $em->persist($location);
        }

        $location->setLocationType($type);
        $em->flush();

        $this->addFlash('success', 'flash.work_location_saved');
        return $this->redirectToRoute('_home');
    }
}
