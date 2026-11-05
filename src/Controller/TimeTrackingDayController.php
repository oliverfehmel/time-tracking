<?php

namespace App\Controller;

use App\Entity\TimeEntry;
use App\Entity\User;
use App\Form\TimeEntryType;
use App\Repository\TimeEntryRepository;
use App\Repository\WorkLocationRepository;
use App\Repository\WorkLocationTypeRepository;
use App\Service\DomainRuleViolation;
use App\Service\TimeEditPolicy;
use App\Service\TimeEntryService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route('/time-tracking', name: '_time_')]
final class TimeTrackingDayController extends AbstractController
{
    public function __construct(
        private readonly TimeEditPolicy $editPolicy,
        private readonly TimeEntryService $timeEntryService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/day/{date}', name: 'day', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['GET'])]
    public function day(
        string $date,
        #[CurrentUser] User $user,
        TimeEntryRepository $repo,
        WorkLocationRepository $locationRepo,
        WorkLocationTypeRepository $locationTypeRepo,
    ): Response {
        if ($redirect = $this->denyIfEntryEditingDisabled()) {
            return $redirect;
        }

        $dayStart = new DateTimeImmutable($date . ' 00:00:00');
        $dayEnd   = $dayStart->modify('+1 day');

        return $this->render('time_tracking/day.html.twig', [
            'day'             => $dayStart,
            'entries'         => $repo->findForUserBetween($user, $dayStart, $dayEnd),
            'editable'        => $this->editPolicy->isMonthEditable($dayStart),
            'workLocation'    => $locationRepo->findForUserOnDate($user, $dayStart),
            'locationTypes'   => $locationTypeRepo->findActive(),
            'defaultLocation' => $locationTypeRepo->findDefault(),
        ]);
    }

    #[Route('/entry/new/{date}', name: 'entry_new', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['GET', 'POST'])]
    public function entryNew(string $date, #[CurrentUser] User $user, Request $request): Response
    {
        if ($redirect = $this->denyIfEntryEditingDisabled()) {
            return $redirect;
        }

        $dayStart = new DateTimeImmutable($date . ' 00:00:00');

        if (!$this->editPolicy->isMonthEditable($dayStart)) {
            $this->addFlash('error', 'flash.month_not_editable');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        $entry = new TimeEntry();
        $entry->setUser($user);
        $entry->setStartedAt($dayStart->setTime(9, 0));
        $entry->setStoppedAt($dayStart->setTime(17, 0));

        $form = $this->createForm(TimeEntryType::class, $entry, ['day_start' => $dayStart]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $adjusted = $this->timeEntryService->create($entry);
            } catch (DomainRuleViolation $violation) {
                $this->addFlash('error', $violation->messageKey);
                return $this->redirectToRoute('_time_day', ['date' => $date]);
            }

            if ($adjusted) {
                $this->addFlash('warning', 'flash.time_range_adjusted');
            }

            $this->addFlash('success', 'flash.time_entry_created');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        return $this->render('time_tracking/entry_form.html.twig', [
            'day'   => $dayStart,
            'form'  => $form,
            'title' => 'time_tracking.entry_add',
        ]);
    }

    #[Route('/entry/{id<\d+>}/edit', name: 'entry_edit', methods: ['GET', 'POST'])]
    public function entryEdit(TimeEntry $entry, #[CurrentUser] User $user, Request $request): Response
    {
        if ($redirect = $this->denyIfEntryEditingDisabled()) {
            return $redirect;
        }

        if ($entry->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $originalStart = $entry->getStartedAt();
        $dayStart      = $originalStart->setTime(0, 0);
        $date          = $dayStart->format('Y-m-d');

        if (!$this->editPolicy->isMonthEditable($dayStart)) {
            $this->addFlash('error', 'flash.month_not_editable');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        $form = $this->createForm(TimeEntryType::class, $entry, ['day_start' => $dayStart]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $adjusted = $this->timeEntryService->update($entry, $originalStart);
            } catch (DomainRuleViolation $violation) {
                $this->addFlash('error', $violation->messageKey);
                return $this->redirectToRoute('_time_day', ['date' => $date]);
            }

            if ($adjusted) {
                $this->addFlash('warning', 'flash.time_range_adjusted');
            }

            $this->addFlash('success', 'flash.time_entry_saved');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        return $this->render('time_tracking/entry_form.html.twig', [
            'day'   => $dayStart,
            'form'  => $form,
            'title' => 'time_tracking.entry_edit',
        ]);
    }

    #[Route('/entry/{id<\d+>}/delete', name: 'entry_delete', methods: ['POST'])]
    public function entryDelete(TimeEntry $entry, #[CurrentUser] User $user, Request $request): Response
    {
        if ($redirect = $this->denyIfEntryEditingDisabled()) {
            return $redirect;
        }

        if ($entry->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $date = $entry->getStartedAt()->format('Y-m-d');

        if (!$this->isCsrfTokenValid('delete_time_entry_' . $entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        try {
            $this->timeEntryService->delete($entry);
        } catch (DomainRuleViolation $violation) {
            $this->addFlash('error', $violation->messageKey);
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        $this->addFlash('success', 'flash.time_entry_deleted');
        return $this->redirectToRoute('_time_day', ['date' => $date]);
    }

    private function denyIfEntryEditingDisabled(): ?Response
    {
        if (!$this->editPolicy->isEditingAllowed()) {
            $this->addFlash('error', $this->translator->trans('settings.users_not_allowed_to_change_time_entries'));
            return $this->redirectToRoute('_home');
        }

        return null;
    }
}
