<?php

namespace App\Api\OpenApi;

use Attribute;
use OpenApi\Attributes as OA;

/**
 * Documents an error response (RFC 9457 problem, schema `Problem`).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class ProblemResponse extends OA\Response
{
    private const array DEFAULT_DESCRIPTIONS = [
        400 => 'Malformed request',
        401 => 'Missing, unknown or expired API token',
        403 => 'Not allowed (admin only, read-only token, edit lock, …)',
        404 => 'Unknown resource or one of another user',
        409 => 'Conflict with the current state',
        415 => 'Body is not JSON',
        422 => 'Validation failed or a business rule was violated',
    ];

    public function __construct(int $status, ?string $description = null)
    {
        parent::__construct(
            response: $status,
            description: $description ?? self::DEFAULT_DESCRIPTIONS[$status] ?? 'Error',
            content: new OA\MediaType(
                mediaType: 'application/problem+json',
                schema: new OA\Schema(ref: '#/components/schemas/Problem'),
            ),
        );
    }
}
