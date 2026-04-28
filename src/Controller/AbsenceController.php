<?php

namespace App\Controller;

use App\Entity\AbsenceRequest;
use App\Entity\User;
use App\Form\AbsenceRequestType;
use App\Repository\AbsenceRequestRepository;
use App\Repository\UserRepository;
use App\Service\AbsenceDayCalculator;
use App\Service\AbsenceNotifier;
use App\Service\AbsenceQuotaService;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AbsenceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AbsenceNotifier $notifier,
    ) {}

    #[Route('/absence/{year<\d{4}>}', name: '_absence_index', methods: ['GET'])]
    public function index(
        int $year,
        #[CurrentUser] User $user,
        AbsenceRequestRepository $requestRepo,
        AbsenceQuotaService $quotaService,
        AbsenceDayCalculator $dayCalc,
    ): Response {

        $rows = array_map(
            fn(AbsenceRequest $r) => [
                'entity' => $r,
                'days'   => $dayCalc->countChargeableDays(
                    $r->getStartDate(),
                    $r->getEndDate(),
                    user: $user,
                ),
            ],
            $requestRepo->findForUserInYear($user, $year),
        );

        return $this->render('absence/index.html.twig', [
            'year'     => $year,
            'requests' => $rows,
            'quota'    => $quotaService->getQuotaOverview($user, $year),
        ]);
    }

    #[Route('/absence/{year<\d{4}>}/new', name: '_absence_new', methods: ['GET', 'POST'])]
    public function new(
        int $year,
        #[CurrentUser] User $user,
        Request $request,
        AbsenceRequestRepository $requestRepo,
        AbsenceDayCalculator $dayCalc,
        UserRepository $userRepo,
        AbsenceQuotaService $quotaService,
    ): Response {

        $absence = new AbsenceRequest();
        $absence->setRequestedBy($user);

        $form = $this->createForm(AbsenceRequestType::class, $absence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $start = $absence->getStartDate();
            $end   = $absence->getEndDate();

            if ($error = $this->validateAbsenceDates($start, $end, $year, $requestRepo, $user)) {
                $form->addError(new FormError($error));
                return $this->render('absence/request_new.html.twig', ['year' => $year, 'form' => $form]);
            }

            if ($error = $quotaService->validateRequestWithinQuota($user, $absence->getType(), $year, $start, $end)) {
                $form->addError(new FormError($error));
                return $this->render('absence/request_new.html.twig', ['year' => $year, 'form' => $form]);
            }

            if (!$absence->getType()->requiresApproval()) {
                $absence->setStatus(AbsenceRequest::STATUS_APPROVED);
            }

            $this->em->persist($absence);
            $this->em->flush();

            $days = $dayCalc->countChargeableDays($start, $end, user: $user);

            if (!$this->notifier->sendCreatedNotification($absence, $userRepo->findAdminEmails(), $year)) {
                $this->addFlash('warning', 'Abwesenheit wurde erstellt, aber die Admin-Benachrichtigung konnte nicht gesendet werden.');
            }

            $this->addFlash('success', sprintf('Abwesenheit beantragt (%d Tage).', $days));
            return $this->redirectToRoute('_absence_index', ['year' => $year]);
        }

        return $this->render('absence/request_new.html.twig', [
            'year' => $year,
            'form' => $form,
        ]);
    }

    #[Route('/absence/{id<\d+>}/cancel', name: '_absence_cancel', methods: ['POST'])]
    public function cancel(AbsenceRequest $absence, #[CurrentUser] User $user, Request $request): Response
    {

        if ($absence->getRequestedBy()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!in_array($absence->getStatus(), [AbsenceRequest::STATUS_PENDING, AbsenceRequest::STATUS_APPROVED], true)) {
            $this->addFlash('error', 'Dieser Antrag kann nicht storniert werden.');
            return $this->redirectToRoute('_absence_index', ['year' => (int) $absence->getStartDate()->format('Y')]);
        }

        if (!$this->isCsrfTokenValid('cancel_absence_'.$absence->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiges CSRF-Token.');
            return $this->redirectToRoute('_absence_index', ['year' => (int) $absence->getStartDate()->format('Y')]);
        }

        $absence->cancel();
        $this->em->flush();

        $this->addFlash('success', 'Abwesenheit storniert.');
        return $this->redirectToRoute('_absence_index', ['year' => (int) $absence->getStartDate()->format('Y')]);
    }

    /**
     * Validiert die Datumslogik beim Erstellen. Gibt die Fehlermeldung zurück oder null wenn alles ok.
     */
    private function validateAbsenceDates(
        DateTimeInterface $start,
        DateTimeInterface $end,
        int $year,
        AbsenceRequestRepository $requestRepo,
        User $user,
    ): ?string {
        if ($end < $start) {
            return 'Enddatum darf nicht vor dem Startdatum liegen.';
        }

        if ((int) $start->format('Y') !== $year && (int) $end->format('Y') !== $year) {
            return 'Abwesenheit muss in das ausgewählte Jahr fallen.';
        }

        if ($requestRepo->hasOverlappingApprovedOrPending($user, $start, $end)) {
            return 'Es gibt bereits eine (ausstehende oder genehmigte) Abwesenheit, die sich mit dem Zeitraum überschneidet.';
        }

        return null;
    }
}
