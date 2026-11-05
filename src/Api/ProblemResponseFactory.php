<?php

namespace App\Api;

use App\Service\DomainRuleViolation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds RFC 9457 `application/problem+json` responses for the API (see ADR 0001).
 */
final readonly class ProblemResponseFactory
{
    public const string CONTENT_TYPE = 'application/problem+json';

    public function __construct(private TranslatorInterface $translator) {}

    /**
     * @param list<array{propertyPath: string, message: string, code: string}> $violations
     * @param array<string,string> $headers
     */
    public function create(int $status, string $code, ?string $detail = null, array $violations = [], array $headers = []): JsonResponse
    {
        $body = [
            'type'   => 'about:blank',
            'title'  => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
            'code'   => $code,
            'detail' => $detail ?? $this->translator->trans('api.problem.' . $code),
        ];

        if ($violations !== []) {
            $body['violations'] = $violations;
        }

        return new JsonResponse($body, $status, ['Content-Type' => self::CONTENT_TYPE] + $headers);
    }

    public function fromApiProblem(ApiProblem $problem): JsonResponse
    {
        $detail = $problem->messageKey !== null ? $this->translator->trans($problem->messageKey, $problem->parameters) : null;

        return $this->create($problem->getStatusCode(), $problem->problemCode, $detail, $problem->violations);
    }

    public function fromDomainRuleViolation(DomainRuleViolation $violation): JsonResponse
    {
        $message    = $violation->trans($this->translator);
        $violations = $violation->propertyPath !== null
            ? [['propertyPath' => $violation->propertyPath, 'message' => $message, 'code' => $violation->rule]]
            : [];

        return $this->create($violation->status, $violation->rule, $message, $violations);
    }

    public function fromConstraintViolations(ConstraintViolationListInterface $list): JsonResponse
    {
        $violations = [];
        foreach ($list as $violation) {
            $violations[] = [
                'propertyPath' => $violation->getPropertyPath(),
                'message'      => (string) $violation->getMessage(),
                'code'         => (string) ($violation->getCode() ?? 'invalid'),
            ];
        }

        return $this->create(422, 'validation_failed', null, $violations);
    }
}
