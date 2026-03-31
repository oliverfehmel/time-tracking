<?php

namespace App\Controller;

use App\Service\AbsenceOverviewBuilder;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AbsenceOverviewController extends AbstractController
{
    public function __construct(
        private readonly AbsenceOverviewBuilder $overviewBuilder,
    ) {}

    #[Route('/absences/overview', name: '_absences_overview', methods: ['GET'])]
    public function overview(Request $request): Response
    {
        $monthStart = $this->parseMonthParam((string) $request->query->get('month', ''));

        return $this->render(
            'absence/overview_month.html.twig',
            $this->overviewBuilder->build($monthStart),
        );
    }

    private function parseMonthParam(string $param): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $param)) {
            return new DateTimeImmutable($param . '-01');
        }

        return new DateTimeImmutable('first day of this month');
    }
}
