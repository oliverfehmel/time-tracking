<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Service\UserYearReportBuilder;
use App\Service\WorkLocationCsvBuilder;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class TimeTrackingController extends AbstractController
{
    public function __construct(
        private readonly UserYearReportBuilder $yearBuilder,
        private readonly SettingsRepository $settingsRepo,
        private readonly WorkLocationCsvBuilder $csvBuilder,
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

    #[Route('/time-tracking/{year<\d{4}>}/work-locations.csv', name: '_time_locations_csv', methods: ['GET'])]
    public function locationsCsv(int $year, #[CurrentUser] User $user): Response
    {
        $months   = $this->yearBuilder->build($user, $year, $this->settingsRepo->getOrCreate());
        $csv      = $this->csvBuilder->build($months);
        $slug     = preg_replace('/[^a-z0-9_-]/i', '_', $user->getEmail());
        $filename = sprintf('arbeitsort-%s-%d.csv', $slug, $year);

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename)
        );

        return $response;
    }
}
