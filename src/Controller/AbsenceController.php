<?php

namespace App\Controller;

use App\Entity\AbsenceRequest;
use App\Entity\User;
use App\Form\AbsenceRequestType;
use App\Repository\AbsenceRequestRepository;
use App\Service\AbsenceDayCalculator;
use App\Service\AbsenceQuotaService;
use App\Service\AbsenceRequestService;
use App\Service\DomainRuleViolation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class AbsenceController extends AbstractController
{
    public function __construct(
        private readonly AbsenceRequestService $absenceService,
        private readonly TranslatorInterface $translator,
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
                'days'   => $dayCalc->countForRequest($r),
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
    ): Response {

        $absence = new AbsenceRequest();
        $absence->setRequestedBy($user);

        $form = $this->createForm(AbsenceRequestType::class, $absence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $notificationSent = $this->absenceService->create($absence, $year);
            } catch (DomainRuleViolation $violation) {
                $form->addError(new FormError($violation->trans($this->translator)));
                return $this->render('absence/request_new.html.twig', ['year' => $year, 'form' => $form]);
            }

            if (!$notificationSent) {
                $this->addFlash('warning', 'absence.request.created_warning');
            }

            $this->addFlash('success', 'absence.request.created_success');
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
        $redirect = $this->redirectToRoute('_absence_index', ['year' => (int) $absence->getStartDate()->format('Y')]);

        if ($absence->getRequestedBy()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('cancel_absence_'.$absence->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
            return $redirect;
        }

        try {
            $this->absenceService->cancel($absence);
        } catch (DomainRuleViolation $violation) {
            $this->addFlash('error', $violation->messageKey);
            return $redirect;
        }

        $this->addFlash('success', 'absence.request.cancelled');
        return $redirect;
    }
}
