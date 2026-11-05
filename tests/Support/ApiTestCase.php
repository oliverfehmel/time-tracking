<?php

namespace App\Tests\Support;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Security\ApiTokenManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for /api/v1 tests: tokens, JSON requests, problem assertions and the OpenAPI contract check
 * (every JSON response is validated against the generated OpenAPI document).
 */
abstract class ApiTestCase extends FunctionalTestCase
{
    private ?string $bearer = null;

    protected function setUp(): void
    {
        parent::setUp();
        // The settings row always exists in a running app; create it up front (getOrCreate() would flush mid-request).
        $this->factory->settings();
    }

    protected function tokenFor(User $user, string $scope = ApiToken::SCOPE_READ_WRITE, ?int $validityDays = null): string
    {
        [, $plain] = static::getContainer()->get(ApiTokenManager::class)->create($user, 'test', $scope, $validityDays);

        return $plain;
    }

    /**
     * Authenticates the following requests as $user with a fresh read/write token.
     */
    protected function actAs(User $user, string $scope = ApiToken::SCOPE_READ_WRITE): User
    {
        $this->bearer = $this->tokenFor($user, $scope);

        return $user;
    }

    protected function withToken(?string $plainToken): void
    {
        $this->bearer = $plainToken;
    }

    /**
     * @param array<string,mixed>|null $json
     * @param array<string,string>     $headers  e.g. ['Accept-Language' => 'en']
     * @return array<string,mixed>|null decoded JSON body
     */
    protected function api(string $method, string $uri, ?array $json = null, array $headers = []): ?array
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if ($this->bearer !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->bearer;
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $content = null;
        if ($json !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
            $content                = json_encode($json, JSON_THROW_ON_ERROR);
        }

        $this->client->request($method, $uri, [], [], $server, $content);
        $response = $this->client->getResponse();

        OpenApiContract::assertResponseMatches(static::getContainer(), $this->client->getRequest(), $response);

        $body = (string) $response->getContent();

        return $body === '' ? null : json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Sends a raw body with any content type (malformed JSON, form data, …); the response is contract-checked as well.
     *
     * @return array<string,mixed>|null decoded JSON body
     */
    protected function apiRaw(string $method, string $uri, string $content, string $contentType): ?array
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => $contentType];
        if ($this->bearer !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->bearer;
        }

        $this->client->request($method, $uri, [], [], $server, $content);
        $response = $this->client->getResponse();
        OpenApiContract::assertResponseMatches(static::getContainer(), $this->client->getRequest(), $response);

        $body = (string) $response->getContent();

        return $body === '' ? null : json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string,mixed> decoded problem body
     */
    protected function assertProblem(int $status, string $code): array
    {
        $response = $this->client->getResponse();
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $body = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($code, $body['code'], (string) $response->getContent());
        self::assertSame($status, $body['status']);
        self::assertNotSame('', $body['detail']);

        return $body;
    }

    /**
     * @param array<string,mixed>|null $body
     */
    protected function assertViolation(?array $body, string $propertyPath, ?string $code = null): void
    {
        foreach ($body['violations'] ?? [] as $violation) {
            if ($violation['propertyPath'] === $propertyPath && ($code === null || $violation['code'] === $code)) {
                self::assertTrue(true);

                return;
            }
        }

        self::fail(sprintf('No violation for "%s"%s in %s', $propertyPath, $code ? " ($code)" : '', json_encode($body)));
    }

    protected function assertStatus(int $status): void
    {
        $response = $this->client->getResponse();
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
    }

    protected function lastResponse(): Response
    {
        return $this->client->getResponse();
    }
}
