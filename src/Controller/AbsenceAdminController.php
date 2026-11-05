<?php

namespace App\Controller;

use App\Entity\AbsenceRequest;
use App\Entity\User;
use App\Repository\AbsenceRequestRepository;
use App\Service\AbsenceDayCalculator;
use App\Service\AbsenceRequestService;
use App\Service\DomainRuleViolation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/absence')]
final class AbsenceAdminController extends AbstractController
{
    public function __construct(
        private readonly AbsenceRequestService $absenceService,
    ) {}

    #[Route('/approvals', name: '_admin_absence_approvals', methods: ['GET'])]
    public function approvals(AbsenceRequestRepository $repo, AbsenceDayCalculator $dayCalc): Response
    {
        $toRows = fn(array $requests) => array_map(
            fn(AbsenceRequest $r) => [
                'entity' => $r,
                'days'   => $dayCalc->countForRequest($r),
            ],
            $requests
        );

        return $this->render('admin/absence/approvals.html.twig', [
            'pending'   => $toRows($repo->findPendingApprovals()),
            'processed' => $toRows($repo->findProcessedApprovals()),
        ]);
    }

    #[Route('/{id<\d+>}/approve', name: '_admin_absence_approve', methods: ['POST'])]
    public function approve(AbsenceRequest $absence, #[CurrentUser] User $admin, Request $request): Response
    {
        if ($error = $this->guardAbsenceAction($absence, $admin)) {
            return $error;
        }

        if (!$this->isCsrfTokenValid('approve_absence_'.$absence->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_admin_absence_approvals');
        }

        if (!$this->absenceService->approve($absence, $admin)) {
            $this->addFlash('warning', 'admin_absence.approved_warning');
        } else {
            $this->addFlash('success', 'admin_absence.approved_success');
        }

        return $this->redirectToRoute('_admin_absence_approvals');
    }

    #[Route('/{id<\d+>}/reject', name: '_admin_absence_reject', methods: ['POST'])]
    public function reject(AbsenceRequest $absence, #[CurrentUser] User $admin, Request $request): Response
    {
        if ($error = $this->guardAbsenceAction($absence, $admin)) {
            return $error;
        }

        if (!$this->isCsrfTokenValid('reject_absence_'.$absence->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_admin_absence_approvals');
        }

        try {
            $mailSent = $this->absenceService->reject($absence, $admin, (string) $request->request->get('reason', ''));
        } catch (DomainRuleViolation $violation) {
            $this->addFlash('error', $violation->messageKey);
            return $this->redirectToRoute('_admin_absence_approvals');
        }

        if (!$mailSent) {
            $this->addFlash('warning', 'admin_absence.rejected_warning');
        } else {
            $this->addFlash('success', 'admin_absence.rejected_success');
        }

        return $this->redirectToRoute('_admin_absence_approvals');
    }

    private function guardAbsenceAction(AbsenceRequest $absence, User $admin): ?Response
    {
        try {
            $this->absenceService->assertDecidable($absence, $admin);
        } catch (DomainRuleViolation $violation) {
            $this->addFlash('error', $violation->messageKey);
            return $this->redirectToRoute('_admin_absence_approvals');
        }

        return null;
    }
}
