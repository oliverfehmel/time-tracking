<?php

namespace App\EventListener;

use App\Api\ApiProblem;
use App\Api\ProblemResponseFactory;
use App\Service\DomainRuleViolation;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

/**
 * Renders every error under /api/v1 as problem response.
 *
 * Runs after the security exception listener (priority 1), which handles authentication and access-denied errors
 * through {@see \App\Security\ApiSecurityResponder}.
 */
final readonly class ApiExceptionListener
{
    private const array HTTP_CODES = [
        400 => 'bad_request',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        415 => 'unsupported_media_type',
        422 => 'validation_failed',
    ];

    public function __construct(
        private ProblemResponseFactory $problems,
        private LoggerInterface $logger,
    ) {}

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
    public function __invoke(ExceptionEvent $event): void
    {
        if (!ApiRequestListener::isApiRequest($event->getRequest())) {
            return;
        }

        $event->setResponse($this->toResponse($event->getThrowable()));
    }

    private function toResponse(Throwable $e): \Symfony\Component\HttpFoundation\Response
    {
        if ($e instanceof ApiProblem) {
            return $this->problems->fromApiProblem($e);
        }

        if ($e instanceof DomainRuleViolation) {
            return $this->problems->fromDomainRuleViolation($e);
        }

        // #[MapRequestPayload] / #[MapQueryString] wrap validation errors in a 422 HttpException
        if ($e->getPrevious() instanceof ValidationFailedException) {
            return $this->problems->fromConstraintViolations($e->getPrevious()->getViolations());
        }

        if ($e instanceof ValidationFailedException) {
            return $this->problems->fromConstraintViolations($e->getViolations());
        }

        if ($e instanceof UniqueConstraintViolationException) {
            return $this->problems->create(409, 'duplicate');
        }

        if ($e instanceof ForeignKeyConstraintViolationException) {
            return $this->problems->create(409, 'still_referenced');
        }

        if ($e instanceof HttpExceptionInterface) {
            $status   = $e->getStatusCode();
            $code     = self::HTTP_CODES[$status] ?? 'http_error';
            $response = $this->problems->create($status, $code);
            $response->headers->add($e->getHeaders());

            return $response;
        }

        $this->logger->error('Unhandled API error: ' . $e->getMessage(), ['exception' => $e]);

        return $this->problems->create(500, 'internal_error');
    }
}
