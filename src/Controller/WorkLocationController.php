<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\WorkLocationTypeRepository;
use App\Service\DomainRuleViolation;
use App\Service\TimeEditPolicy;
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
#[Route('/time-tracking/day', name: '_time_location_')]
final class WorkLocationController extends AbstractController
{
    public function __construct(
        private readonly WorkLocationService $workLocationService,
        private readonly WorkLocationTypeRepository $typeRepo,
        private readonly TimeEditPolicy $editPolicy,
    ) {}

    #[Route('/{date}/location', name: 'set', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['POST'])]
    public function set(
        string $date,
        #[CurrentUser] User $user,
        Request $request,
        TranslatorInterface $translator,
    ): Response {
        if (!$this->editPolicy->isEditingAllowed()) {
            $this->addFlash('error', $translator->trans('settings.users_not_allowed_to_change_time_entries'));
            return $this->redirectToRoute('_home');
        }

        if (!$this->isCsrfTokenValid('set_work_location_' . $date, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        try {
            $this->workLocationService->setForDay(
                $user,
                new DateTimeImmutable($date . ' 00:00:00'),
                $this->typeRepo->find((int) $request->request->get('location_type_id')),
            );
        } catch (DomainRuleViolation $violation) {
            $this->addFlash('error', $violation->messageKey);
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        $this->addFlash('success', 'flash.work_location_saved');
        return $this->redirectToRoute('_time_day', ['date' => $date]);
    }
}
