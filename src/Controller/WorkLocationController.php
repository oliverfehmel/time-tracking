<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WorkLocation;
use App\Repository\WorkLocationRepository;
use App\Repository\WorkLocationTypeRepository;
use App\Repository\SettingsRepository;
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
#[Route('/time-tracking/day', name: '_time_location_')]
final class WorkLocationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WorkLocationRepository $locationRepo,
        private readonly WorkLocationTypeRepository $typeRepo,
        private readonly SettingsRepository $settingsRepo,
        private readonly TimeTrackingCalculator $calc,
    ) {}

    #[Route('/{date}/location', name: 'set', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['POST'])]
    public function set(
        string $date,
        #[CurrentUser] User $user,
        Request $request,
        TranslatorInterface $translator,
    ): Response {
        if (!$this->settingsRepo->getOrCreate()->isUsersAreAllowedToChangeTimeEntries()) {
            $this->addFlash('error', $translator->trans('settings.users_not_allowed_to_change_time_entries'));
            return $this->redirectToRoute('_home');
        }

        if (!$this->isCsrfTokenValid('set_work_location_' . $date, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiges CSRF-Token.');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        $dayStart = new DateTimeImmutable($date . ' 00:00:00');

        if (!$this->calc->isEditableMonth($dayStart, new DateTimeImmutable())) {
            $this->addFlash('error', 'Dieser Monat ist nicht mehr bearbeitbar.');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        $typeId = (int) $request->request->get('location_type_id');
        $type = $this->typeRepo->find($typeId);

        if (!$type || !$type->isActive()) {
            $this->addFlash('error', 'Ungültiger Arbeitsort-Typ.');
            return $this->redirectToRoute('_time_day', ['date' => $date]);
        }

        $location = $this->locationRepo->findForUserOnDate($user, $dayStart);

        if ($location === null) {
            $location = new WorkLocation();
            $location->setUser($user);
            $location->setDate($dayStart);
            $this->em->persist($location);
        }

        $location->setLocationType($type);
        $this->em->flush();

        $this->addFlash('success', 'Arbeitsort gespeichert.');
        return $this->redirectToRoute('_time_day', ['date' => $date]);
    }
}
