<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\WorkLocationTypeRepository;
use App\Service\DashboardDataBuilder;
use App\Service\DomainRuleViolation;
use App\Service\TimeEditPolicy;
use App\Service\TimeEntryService;
use App\Service\WorkLocationService;
use DateTimeImmutable;
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
        private readonly TimeEntryService $timeEntryService,
    ) {}

    #[Route('/', name: '_home', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('dashboard/index.html.twig', $this->dashboardBuilder->build($user));
    }

    #[Route('/time/start', name: '_time_start', methods: ['POST'])]
    public function start(#[CurrentUser] User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('time_start', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_home');
        }

        try {
            $this->timeEntryService->start($user);
        } catch (DomainRuleViolation $violation) {
            $this->addFlash('error', $violation->messageKey);
            return $this->redirectToRoute('_home');
        }

        $this->addFlash('success', 'flash.timer_started');
        return $this->redirectToRoute('_home');
    }

    #[Route('/time/stop', name: '_time_stop', methods: ['POST'])]
    public function stop(#[CurrentUser] User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('time_stop', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_home');
        }

        try {
            $this->timeEntryService->stop($user);
        } catch (DomainRuleViolation $violation) {
            $this->addFlash('error', $violation->messageKey);
            return $this->redirectToRoute('_home');
        }

        $this->addFlash('success', 'flash.timer_stopped');
        return $this->redirectToRoute('_home');
    }

    #[Route('/time/work-location', name: '_time_location_dashboard', methods: ['POST'])]
    public function setTodayWorkLocation(
        #[CurrentUser] User $user,
        Request $request,
        WorkLocationTypeRepository $typeRepo,
        WorkLocationService $workLocationService,
        TimeEditPolicy $editPolicy,
        TranslatorInterface $translator,
    ): Response {
        if (!$editPolicy->isEditingAllowed()) {
            $this->addFlash('error', $translator->trans('settings.users_not_allowed_to_change_time_entries'));
            return $this->redirectToRoute('_home');
        }

        if (!$this->isCsrfTokenValid('set_work_location_today', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_home');
        }

        try {
            $workLocationService->setForDay(
                $user,
                new DateTimeImmutable('today'),
                $typeRepo->find((int) $request->request->get('location_type_id')),
            );
        } catch (DomainRuleViolation $violation) {
            $this->addFlash('error', $violation->messageKey);
            return $this->redirectToRoute('_home');
        }

        $this->addFlash('success', 'flash.work_location_saved');
        return $this->redirectToRoute('_home');
    }
}
