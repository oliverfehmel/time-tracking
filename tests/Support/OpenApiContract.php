<?php

namespace App\Tests\Support;

use PHPUnit\Framework\Assert;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks API responses against the OpenAPI document that NelmioApiDocBundle generates:
 * the operation and status code must be documented and the JSON body must match the schema
 * (types, required properties, enums, no undocumented properties).
 */
final class OpenApiContract
{
    /** @var array<string,mixed>|null */
    private static ?array $spec = null;

    /**
     * @return array<string,mixed>
     */
    public static function spec(ContainerInterface $container): array
    {
        if (self::$spec === null) {
            $generator  = $container->get('nelmio_api_doc.generator.default');
            self::$spec = json_decode($generator->generate()->toJson(), true, flags: JSON_THROW_ON_ERROR);
        }

        return self::$spec;
    }

    public static function assertResponseMatches(ContainerInterface $container, Request $request, Response $response): void
    {
        $spec      = self::spec($container);
        $operation = self::findOperation($spec, $request->getPathInfo(), strtolower($request->getMethod()));
        $status    = (string) $response->getStatusCode();
        $context   = sprintf('%s %s → %s', $request->getMethod(), $request->getPathInfo(), $status);

        if ($operation === null) {
            // Unknown routes (404 from the router) are not part of the document.
            Assert::assertSame(404, $response->getStatusCode(), "Undocumented operation: $context");

            return;
        }

        $documented = $operation['responses'][$status] ?? $operation['responses']['default'] ?? null;
        Assert::assertNotNull($documented, "Undocumented status code: $context — " . $response->getContent());

        $body = (string) $response->getContent();
        if ($body === '') {
            Assert::assertArrayNotHasKey('content', $documented, "Documented body missing: $context");

            return;
        }

        $contentType = strtok((string) $response->headers->get('Content-Type'), ';');
        $media       = $documented['content'][$contentType] ?? null;
        Assert::assertNotNull($media, "Undocumented content type $contentType: $context");

        $errors = [];
        self::validate($spec, $media['schema'] ?? [], json_decode($body, true, flags: JSON_THROW_ON_ERROR), '$', $errors);
        Assert::assertSame([], $errors, "Response does not match the OpenAPI schema: $context\n" . $body);
    }

    /**
     * @param array<string,mixed> $spec
     * @return array<string,mixed>|null
     */
    private static function findOperation(array $spec, string $path, string $method): ?array
    {
        foreach ($spec['paths'] ?? [] as $template => $item) {
            $regex = '#^' . preg_replace('#\\\{[^/]+\\\}#', '[^/]+', preg_quote($template, '#')) . '$#';
            if (preg_match($regex, $path) && isset($item[$method])) {
                return $item[$method];
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $spec
     * @param array<string,mixed> $schema
     * @param list<string>        $errors
     */
    private static function validate(array $spec, array $schema, mixed $value, string $path, array &$errors): void
    {
        if (isset($schema['$ref'])) {
            self::validate($spec, self::resolve($spec, $schema['$ref']), $value, $path, $errors);

            return;
        }

        if ($value === null) {
            $types = (array) ($schema['type'] ?? []);
            $nullableVariant = array_filter(
                $schema['oneOf'] ?? $schema['anyOf'] ?? [],
                static fn($s) => ($s['type'] ?? null) === 'null' || ($s['nullable'] ?? false),
            );
            if (!($schema['nullable'] ?? false) && !in_array('null', $types, true) && $nullableVariant === [] && $schema !== []) {
                $errors[] = "$path: null is not allowed";
            }

            return;
        }

        foreach ($schema['allOf'] ?? [] as $sub) {
            self::validate($spec, $sub, $value, $path, $errors);
        }

        foreach (['oneOf', 'anyOf'] as $keyword) {
            if (isset($schema[$keyword])) {
                $matches = 0;
                foreach ($schema[$keyword] as $sub) {
                    $subErrors = [];
                    self::validate($spec, $sub, $value, $path, $subErrors);
                    $matches += $subErrors === [] ? 1 : 0;
                }
                if ($matches === 0) {
                    $errors[] = "$path: matches none of $keyword";
                }
            }
        }

        $types = array_values(array_diff((array) ($schema['type'] ?? []), ['null']));
        if ($types !== [] && !self::matchesAnyType($value, $types)) {
            $errors[] = sprintf('%s: expected %s, got %s', $path, implode('|', $types), get_debug_type($value));

            return;
        }

        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = sprintf('%s: %s is not one of %s', $path, json_encode($value), json_encode($schema['enum']));
        }

        if (is_array($value) && array_is_list($value) && in_array('array', $types, true)) {
            foreach ($value as $i => $item) {
                self::validate($spec, $schema['items'] ?? [], $item, "{$path}[$i]", $errors);
            }

            return;
        }

        if (is_array($value) && (in_array('object', $types, true) || isset($schema['properties']))) {
            foreach ($schema['required'] ?? [] as $required) {
                if (!array_key_exists($required, $value)) {
                    $errors[] = "$path.$required: required property missing";
                }
            }
            $properties = $schema['properties'] ?? [];
            foreach ($value as $name => $propertyValue) {
                if (!isset($properties[$name])) {
                    if ($properties !== [] && !($schema['additionalProperties'] ?? false)) {
                        $errors[] = "$path.$name: undocumented property";
                    }
                    continue;
                }
                self::validate($spec, $properties[$name], $propertyValue, "$path.$name", $errors);
            }
        }
    }

    /**
     * @param list<string> $types
     */
    private static function matchesAnyType(mixed $value, array $types): bool
    {
        foreach ($types as $type) {
            $ok = match ($type) {
                'string'  => is_string($value),
                'integer' => is_int($value),
                'number'  => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                'array'   => is_array($value) && array_is_list($value),
                'object'  => is_array($value) && ($value === [] || !array_is_list($value)),
                default   => true,
            };
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $spec
     * @return array<string,mixed>
     */
    private static function resolve(array $spec, string $ref): array
    {
        $node = $spec;
        foreach (explode('/', substr($ref, 2)) as $segment) {
            $node = $node[$segment] ?? throw new \RuntimeException("Unresolvable \$ref $ref");
        }

        return $node;
    }
}
