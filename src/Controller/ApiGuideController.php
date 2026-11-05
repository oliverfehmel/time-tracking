<?php

namespace App\Controller;

use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Explains how the REST API works and links to Swagger UI (/api/docs).
 */
#[IsGranted('ROLE_USER')]
final class ApiGuideController extends AbstractController
{
    #[Route('/api', name: '_api_guide', methods: ['GET'])]
    public function guide(Request $request, SettingsRepository $settingsRepo): Response
    {
        return $this->render('api/guide.html.twig', [
            'baseUrl'         => $request->getSchemeAndHttpHost() . $request->getBasePath() . '/api/v1',
            'halfDaysEnabled' => $settingsRepo->getOrCreate()->isHalfDaysEnabled(),
        ]);
    }
}
