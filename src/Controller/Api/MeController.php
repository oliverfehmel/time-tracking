<?php

namespace App\Controller\Api;

use App\Api\OpenApi\DataResponse;
use App\Api\OpenApi\ProblemResponse;
use App\Api\View\MeView;
use App\Entity\ApiToken;
use App\Repository\SettingsRepository;
use App\Security\ApiTokenHandler;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
#[OA\Tag(name: 'Me')]
#[ProblemResponse(400)]
#[ProblemResponse(401)]
final class MeController extends AbstractApiController
{
    #[Route('/me', name: 'api_v1_me', methods: ['GET'])]
    #[OA\Get(summary: 'The authenticated user, the token in use and feature flags')]
    #[DataResponse(MeView::class)]
    public function me(Request $request, SettingsRepository $settingsRepo): JsonResponse
    {
        /** @var ApiToken $token */
        $token = $request->attributes->get(ApiTokenHandler::REQUEST_ATTRIBUTE);

        return $this->item(MeView::from($this->currentUser(), $token, $settingsRepo->getOrCreate()));
    }
}
