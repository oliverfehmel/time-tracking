<?php

namespace App\Controller;

use App\Entity\TimeEntry;
use App\Entity\User;
use App\Form\TimeEntryType;
use App\Repository\SettingsRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\WorkLocationRepository;
use App\Repository\WorkLocationTypeRepository;
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
#[Route('/time-tracking', name: '_time_')]
final class TimeTrackingDayController extends AbstractController
{
    public function __construct(
        private readonly SettingsRepository $settingsRepo,
        private readonly TimeTrackingCalculator $calc,
    ) {}

    #[Route('/day/{date}', name: 'day', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['GET'])]
    public function day(
        string $date,
        #[CurrentUser] User $user,
        TimeEntryRepository $repo,
        WorkLocationRepository $locationRepo,
        WorkLocationTypeRepository $locationTypeRepo,
        TranslatorInterface $translator,
    ): Response {
        if ($redirect = $this->denyIfEntryEditingDisabled($translator)) {
            return $redirect;
        }

        $now      = new DateTimeImmutable();
        $dayStart = new DateTimeImmutable($date . ' 00:00:00');
        $dayEnd   = $dayStart->modify('+1 day');

        return $this->render('time_tracking/day.html.twig', [
            'day'             => $dayStart,
            'entries'         => $repo->findForUserBetween($user, $dayStart, $dayEnd),
            'editable'        => $this->calc->isEditableMonth($dayStart, $now),
            'workLocation'    => $locationRepo->findForUserOnDate($user, $dayStart),
            'locationTypes'   => $locationTypeRepo->findActive(),
            'defaultLocation' => $locationTypeRepo->findDefault(),
        ]);
    }

    #[Route('/entry/new/{date}', name: 'entry_new', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['GET', 'POST'])]
    public function entryNew(
        string $date,
        #[CurrentUser] User $user,
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        if ($redirect = $this->denyIfEntryEditingDisabled($translator)) {
            return $redirect;
        }

        $now      = new DateTimeImmutable();
        $dayStart = new DateTimeImmutable($date . ' 00:00:00');

        if (!$this->calc->isEditableMonth($dayStart, $now)) {
            $this->addFlash('error', 'Dieser Monat ist nicht mehr bearbeitbar.');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        $entry = new TimeEntry();
        $entry->setUser($user);
        $entry->setStartedAt($dayStart->setTime(9, 0));
        $entry->setStoppedAt($dayStart->setTime(17, 0));

        $form = $this->createForm(TimeEntryType::class, $entry, ['day_start' => $dayStart]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entry);
            $em->flush();

            $this->addFlash('success', 'Eintrag wurde angelegt.');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        return $this->render('time_tracking/entry_form.html.twig', [
            'day'   => $dayStart,
            'form'  => $form,
            'title' => 'Eintrag hinzufügen',
        ]);
    }

    #[Route('/entry/{id<\d+>}/edit', name: 'entry_edit', methods: ['GET', 'POST'])]
    public function entryEdit(
        TimeEntry $entry,
        #[CurrentUser] User $user,
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        if ($redirect = $this->denyIfEntryEditingDisabled($translator)) {
            return $redirect;
        }

        if ($entry->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $now      = new DateTimeImmutable();
        $dayStart = $entry->getStartedAt()->setTime(0, 0);
        $date     = $dayStart->format('Y-m-d');

        if (!$this->calc->isEditableMonth($dayStart, $now)) {
            $this->addFlash('error', 'Dieser Monat ist nicht mehr bearbeitbar.');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        $form = $this->createForm(TimeEntryType::class, $entry, ['day_start' => $dayStart]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Eintrag wurde gespeichert.');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        return $this->render('time_tracking/entry_form.html.twig', [
            'day'   => $dayStart,
            'form'  => $form,
            'title' => 'Eintrag bearbeiten',
        ]);
    }

    #[Route('/entry/{id<\d+>}/delete', name: 'entry_delete', methods: ['POST'])]
    public function entryDelete(
        TimeEntry $entry,
        #[CurrentUser] User $user,
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        if ($redirect = $this->denyIfEntryEditingDisabled($translator)) {
            return $redirect;
        }

        if ($entry->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $now      = new DateTimeImmutable();
        $dayStart = $entry->getStartedAt()->setTime(0, 0);
        $date     = $dayStart->format('Y-m-d');

        if (!$this->calc->isEditableMonth($dayStart, $now)) {
            $this->addFlash('error', 'Dieser Monat ist nicht mehr bearbeitbar.');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        if (!$this->isCsrfTokenValid('delete_time_entry_' . $entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiges CSRF-Token.');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        $em->remove($entry);
        $em->flush();

        $this->addFlash('success', 'Eintrag wurde gelöscht.');
        return $this->redirectToRoute('_time_day', ['date' => $date]);
    }

    private function denyIfEntryEditingDisabled(TranslatorInterface $translator): ?Response
    {
        if (!$this->settingsRepo->getOrCreate()->isUsersAreAllowedToChangeTimeEntries()) {
            $this->addFlash('error', $translator->trans('settings.users_not_allowed_to_change_time_entries'));
            return $this->redirectToRoute('_home');
        }

        return null;
    }
}
