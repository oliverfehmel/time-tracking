<?php

namespace App\Controller;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Form\ApiTokenCreateType;
use App\Repository\ApiTokenRepository;
use App\Security\ApiTokenManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Personal API tokens of the logged-in user (Profile → API tokens).
 */
#[IsGranted('ROLE_USER')]
#[Route('/profile/api-tokens', name: '_api_tokens')]
final class ApiTokenController extends AbstractController
{
    public function __construct(
        private readonly ApiTokenManager $tokenManager,
        private readonly ApiTokenRepository $tokenRepo,
    ) {}

    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        // An admin impersonating a user must not create a token that outlives the impersonation.
        $impersonating = $this->isGranted('IS_IMPERSONATOR');

        $form = $this->createForm(ApiTokenCreateType::class);
        $form->handleRequest($request);

        $plainToken = null;
        $status     = Response::HTTP_OK;

        if ($form->isSubmitted() && $impersonating) {
            $this->addFlash('error', 'api_token.impersonation_forbidden');
            return $this->redirectToRoute('_api_tokens');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            [, $plainToken] = $this->tokenManager->create(
                $user,
                (string) $form->get('name')->getData(),
                (string) $form->get('scope')->getData(),
                ApiTokenCreateType::validityDays((string) $form->get('validity')->getData()),
            );
            $form = $this->createForm(ApiTokenCreateType::class);
        } elseif ($form->isSubmitted()) {
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        // The plain token is rendered directly in this response (the form opts out of Turbo) and never stored.
        // That page must not come back from the browser's or Turbo's cache (see the template).
        $response = new Response(status: $status);
        if ($plainToken !== null) {
            $response->headers->set('Cache-Control', 'no-store, private');
            // Keep the session listener from replacing it with "private, max-age=0, must-revalidate"
            $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        }

        return $this->render('profile/api_tokens.html.twig', [
            'form'          => $form,
            'tokens'        => $this->tokenRepo->findForUser($user),
            'plainToken'    => $plainToken,
            'impersonating' => $impersonating,
        ], $response);
    }

    #[Route('/{id<\d+>}/revoke', name: '_revoke', methods: ['POST'])]
    public function revoke(ApiToken $token, #[CurrentUser] User $user, Request $request): Response
    {
        if ($token->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('revoke_api_token_' . $token->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
            return $this->redirectToRoute('_api_tokens');
        }

        $this->tokenManager->revoke($token);
        $this->addFlash('success', 'api_token.revoked');

        return $this->redirectToRoute('_api_tokens');
    }
}
