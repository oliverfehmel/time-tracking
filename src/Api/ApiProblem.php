<?php

namespace App\Api;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * An API error that is rendered as RFC 9457 problem response by {@see \App\EventListener\ApiExceptionListener}.
 */
final class ApiProblem extends RuntimeException implements HttpExceptionInterface
{
    /**
     * @param string                           $problemCode machine-readable code (`code` in the response)
     * @param string|null                      $messageKey  translation key for `detail`; defaults to "api.problem.<code>"
     * @param list<array{propertyPath: string, message: string, code: string}> $violations
     */
    public function __construct(
        private readonly int $status,
        public readonly string $problemCode,
        public readonly ?string $messageKey = null,
        public readonly array $parameters = [],
        public readonly array $violations = [],
    ) {
        parent::__construct($messageKey ?? $problemCode);
    }

    public static function forbidden(string $code = 'forbidden'): self
    {
        return new self(403, $code);
    }

    public static function notFound(): self
    {
        return new self(404, 'not_found');
    }

    public static function validation(string $propertyPath, string $message, string $code): self
    {
        return new self(422, 'validation_failed', violations: [
            ['propertyPath' => $propertyPath, 'message' => $message, 'code' => $code],
        ]);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getHeaders(): array
    {
        return [];
    }
}
