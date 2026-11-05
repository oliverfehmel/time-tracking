<?php

namespace App\EventListener;

use App\Api\ApiProblem;
use App\Entity\ApiToken;
use App\Security\ApiTokenHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Request handling specific to /api/v1:
 * - picks the language for messages from Accept-Language (de/en), before translators read the request locale;
 * - rejects writes with read-only tokens, right after the firewall has authenticated the token.
 */
final readonly class ApiRequestListener
{
    public const array LOCALES = ['de', 'en'];

    public function __construct(
        #[Autowire('%kernel.default_locale%')]
        private string $defaultLocale,
    ) {}

    public static function isApiRequest(Request $request): bool
    {
        return preg_match('#^/api/v1(/|$)#', $request->getPathInfo()) === 1;
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
    public function setLocale(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !self::isApiRequest($request)) {
            return;
        }

        $preferred = $request->headers->has('Accept-Language') ? $request->getPreferredLanguage(self::LOCALES) : null;
        $request->setLocale($preferred ?? $this->defaultLocale);
    }

    /**
     * Runs after the firewall (priority 8), which authenticates the token and stores it on the request.
     */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
    public function enforceTokenScope(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !self::isApiRequest($request) || $request->isMethodSafe()) {
            return;
        }

        $token = $request->attributes->get(ApiTokenHandler::REQUEST_ATTRIBUTE);
        if ($token instanceof ApiToken && !$token->allowsWrite()) {
            throw ApiProblem::forbidden('read_only_token');
        }
    }
}
