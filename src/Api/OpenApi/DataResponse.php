<?php

namespace App\Api\OpenApi;

use Attribute;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Documents a successful response in the API envelope: `{"data": <model>}` or, for collections,
 * `{"data": [<model>], "meta": PaginationMeta}`. Optional `meta` properties of write responses can be listed.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class DataResponse extends OA\Response
{
    /**
     * @param class-string           $model
     * @param array<string,string>   $meta  extra `meta` properties (name => description) of write responses, boolean
     */
    public function __construct(string $model, int $status = 200, string $description = 'OK', bool $collection = false, array $meta = [])
    {
        $data = $collection
            ? new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: $model)))
            : new OA\Property(property: 'data', ref: new Model(type: $model));

        $properties = [$data];
        $required   = ['data'];

        if ($collection) {
            $properties[] = new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta');
            $required[]   = 'meta';
        } elseif ($meta !== []) {
            $properties[] = new OA\Property(
                property: 'meta',
                properties: array_map(
                    static fn(string $name, string $text) => new OA\Property(property: $name, description: $text, type: 'boolean'),
                    array_keys($meta),
                    $meta,
                ),
                type: 'object',
            );
        }

        parent::__construct(
            response: $status,
            description: $description,
            content: new OA\JsonContent(required: $required, properties: $properties, type: 'object'),
        );
    }
}
