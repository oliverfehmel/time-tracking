<?php

namespace App\Controller;

use App\Entity\AbsenceRequest;
use App\Entity\User;
use App\Repository\AbsenceRequestRepository;
use App\Service\AbsenceDayCalculator;
use App\Service\AbsenceNotifier;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly AbsenceNotifier $notifier,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/approvals', name: '_admin_absence_approvals', methods: ['GET'])]
    public function approvals(AbsenceRequestRepository $repo, AbsenceDayCalculator $dayCalc): Response
    {
        $toRows = fn(array $requests) => array_map(
            fn(AbsenceRequest $r) => [
                'entity' => $r,
                'days'   => $dayCalc->countChargeableDays(
                    $r->getStartDate(),
                    $r->getEndDate(),
                    user: $r->getRequestedBy(),  // war im Original inkonsistent (null)
                ),
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

        $absence->approve($admin);
        $this->em->flush();

        if (!$this->notifier->sendDecision($absence, approved: true)) {
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

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'admin_absence.reason_required');
            return $this->redirectToRoute('_admin_absence_approvals');
        }

        $absence->reject($admin, $reason);
        $this->em->flush();

        if (!$this->notifier->sendDecision($absence, approved: false)) {
            $this->addFlash('warning', 'admin_absence.rejected_warning');
        } else {
            $this->addFlash('success', 'admin_absence.rejected_success');
        }

        return $this->redirectToRoute('_admin_absence_approvals');
    }

    private function guardAbsenceAction(AbsenceRequest $absence, User $admin): ?Response
    {
        if ($absence->getStatus() !== AbsenceRequest::STATUS_PENDING) {
            $this->addFlash('error', 'admin_absence.only_pending');
            return $this->redirectToRoute('_admin_absence_approvals');
        }

        if ($absence->getRequestedBy()->getId() === $admin->getId()) {
            $this->addFlash('error', 'admin_absence.own_request_error');
            return $this->redirectToRoute('_admin_absence_approvals');
        }

        return null;
    }
}
