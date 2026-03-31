<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Repository\UserRepository;
use App\Service\UserYearReportBuilder;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/time-tracking', name: '_admin_time_')]
final class AdminTimeTrackingController extends AbstractController
{
    public function __construct(
        private readonly UserYearReportBuilder $reportBuilder,
    ) {}

    #[Route('', name: 'users', methods: ['GET'])]
    public function users(UserRepository $userRepository): Response
    {
        return $this->render('admin/time_tracking/users.html.twig', [
            'users' => $userRepository->findBy([], ['name' => 'ASC', 'email' => 'ASC']),
        ]);
    }

    #[Route('/{id<\d+>}/{year<\d{4}>}', name: 'user_year', methods: ['GET'])]
    public function userYear(User $user, int $year, SettingsRepository $settingsRepo): Response
    {
        $now         = new DateTimeImmutable('now');
        $currentYear = (int) $now->format('Y');
        $months      = $this->reportBuilder->build($user, $year, $settingsRepo->getOrCreate());

        return $this->render('admin/time_tracking/user_year.html.twig', [
            'userEntity' => $user,
            'year'       => $year,
            'years'      => range($currentYear - 2, $currentYear + 1),
            'months'     => $months,
        ]);
    }
}
