<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Service\UserYearReportBuilder;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class TimeTrackingController extends AbstractController
{
    public function __construct(
        private readonly UserYearReportBuilder $yearBuilder,
        private readonly SettingsRepository $settingsRepo,
    ) {}

    #[Route('/time-tracking/{year<\d{4}>}', name: '_time_index', methods: ['GET'])]
    public function index(
        int $year,
        #[CurrentUser] User $user,
        Request $request,
    ): Response {
        $now         = new DateTimeImmutable();
        $currentYear = (int) $now->format('Y');

        return $this->render('time_tracking/index.html.twig', [
            'year'       => $year,
            'years'      => range($currentYear - 2, $currentYear + 1),
            'months'     => $this->yearBuilder->build($user, $year, $this->settingsRepo->getOrCreate()),
            'open_month' => $request->query->getInt('month', 0) ?: false,
        ]);
    }
}
