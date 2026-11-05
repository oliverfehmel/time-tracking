<?php

namespace App\Security;

use App\Api\ProblemResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Problem responses for the `api` firewall: 401 without/with an invalid token, 403 when access is denied.
 */
final readonly class ApiSecurityResponder implements AuthenticationEntryPointInterface, AuthenticationFailureHandlerInterface, AccessDeniedHandlerInterface
{
    public function __construct(private ProblemResponseFactory $problems) {}

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->problems->create(401, 'unauthorized', headers: ['WWW-Authenticate' => 'Bearer realm="time-tracking"']);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->problems->create(401, 'unauthorized', headers: [
            'WWW-Authenticate' => 'Bearer realm="time-tracking", error="invalid_token"',
        ]);
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        return $this->problems->create(403, 'forbidden');
    }
}
