<?php

namespace App\Tests\Api;

use App\Entity\ApiToken;
use App\Repository\ApiTokenRepository;
use App\Security\ApiTokenManager;
use App\Tests\Support\ApiTestCase;

final class AuthenticationTest extends ApiTestCase
{
    public function testMissingTokenIsUnauthorized(): void
    {
        $this->api('GET', '/api/v1/me');

        $this->assertProblem(401, 'unauthorized');
        self::assertStringStartsWith('Bearer', (string) $this->lastResponse()->headers->get('WWW-Authenticate'));
    }

    public function testUnknownAndMalformedTokensAreUnauthorized(): void
    {
        foreach (['tt_' . str_repeat('0', 64), 'not-a-token'] as $token) {
            $this->withToken($token);
            $this->api('GET', '/api/v1/me');
            $this->assertProblem(401, 'unauthorized');
        }
    }

    public function testValidTokenAuthenticatesOwner(): void
    {
        $user = $this->actAs($this->factory->user());

        $body = $this->api('GET', '/api/v1/me');

        $this->assertStatus(200);
        self::assertSame($user->getId(), $body['data']['user']['id']);
        self::assertSame('read_write', $body['data']['token']['scope']);
        self::assertFalse($body['data']['features']['halfDaysEnabled']);
        self::assertTrue($body['data']['features']['timeEntryEditingAllowed']);
    }

    public function testExpiredTokenIsUnauthorized(): void
    {
        self::mockTime('2026-01-01 12:00:00');
        $token = $this->tokenFor($this->factory->user(), validityDays: 30);

        self::mockTime('2026-02-01 12:00:00');
        $this->withToken($token);
        $this->api('GET', '/api/v1/me');

        $this->assertProblem(401, 'unauthorized');
    }

    public function testRevokedTokenIsUnauthorized(): void
    {
        $user  = $this->factory->user();
        $token = $this->tokenFor($user);
        $entity = static::getContainer()->get(ApiTokenRepository::class)->findOneByHash(ApiTokenManager::hash($token));
        static::getContainer()->get(ApiTokenManager::class)->revoke($entity);

        $this->withToken($token);
        $this->api('GET', '/api/v1/me');

        $this->assertProblem(401, 'unauthorized');
    }

    public function testReadOnlyTokenCannotWrite(): void
    {
        $this->actAs($this->factory->user(), ApiToken::SCOPE_READ);

        $this->api('GET', '/api/v1/me');
        $this->assertStatus(200);

        $this->api('POST', '/api/v1/timer/start');
        $this->assertProblem(403, 'read_only_token');
    }

    public function testSessionLoginDoesNotAuthenticateApi(): void
    {
        $user = $this->factory->user();
        $user->setPassword(static::getContainer()->get('security.user_password_hasher')->hashPassword($user, 'secret123'));
        $this->em->flush();

        // Real form login (loginUser() would put the token straight into the test container's token storage)
        $crawler = $this->client->request('GET', '/login');
        $form    = $crawler->filter('form')->form(['_username' => $user->getEmail(), '_password' => 'secret123']);
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $this->api('GET', '/api/v1/me');

        $this->assertProblem(401, 'unauthorized');
    }

    public function testTokenDoesNotAuthenticateUi(): void
    {
        $token = $this->tokenFor($this->factory->user());

        $this->client->request('GET', '/', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        self::assertResponseRedirects('/login');
    }

    public function testLastUsedIsRecorded(): void
    {
        self::mockTime('2026-11-05 10:00:00');
        $user  = $this->factory->user();
        $token = $this->tokenFor($user);
        $this->withToken($token);

        $this->api('GET', '/api/v1/me');

        $this->em->clear();
        $entity = static::getContainer()->get(ApiTokenRepository::class)->findOneByHash(ApiTokenManager::hash($token));
        self::assertSame('2026-11-05 10:00:00', $entity->getLastUsedAt()?->format('Y-m-d H:i:s'));
    }

    public function testDemotedAdminLosesAdminEndpoints(): void
    {
        $admin = $this->actAs($this->factory->admin());
        $this->api('POST', '/api/v1/users', ['email' => 'new@example.test', 'name' => 'New', 'password' => 'secret123']);
        $this->assertStatus(201);

        $admin->setRoles([]);
        $this->em->flush();

        $this->api('POST', '/api/v1/users', ['email' => 'other@example.test', 'name' => 'Other', 'password' => 'secret123']);
        $this->assertProblem(403, 'forbidden');
    }

    public function testErrorMessagesFollowAcceptLanguage(): void
    {
        $de = $this->api('GET', '/api/v1/me', headers: ['Accept-Language' => 'de-DE,de;q=0.9']);
        $en = $this->api('GET', '/api/v1/me', headers: ['Accept-Language' => 'en-US']);

        self::assertNotSame($de['detail'], $en['detail']);
        self::assertSame('A valid API token is required.', $en['detail']);
    }

    public function testUnknownRouteIsProblem(): void
    {
        $this->actAs($this->factory->user());

        $this->api('GET', '/api/v1/does-not-exist', headers: ['Accept-Language' => 'en']);
        $body = $this->assertProblem(404, 'not_found');
        self::assertSame('The resource does not exist.', $body['detail']);

        $this->api('GET', '/api/v1/does-not-exist', headers: ['Accept-Language' => 'de']);
        $body = $this->assertProblem(404, 'not_found');
        self::assertSame('Die Ressource existiert nicht.', $body['detail']);
    }

    public function testWrongMethodIsTranslatedProblem(): void
    {
        $this->actAs($this->factory->user());

        $body = $this->apiRaw('DELETE', '/api/v1/me', '', 'application/json');

        self::assertSame(405, $this->lastResponse()->getStatusCode());
        self::assertSame('method_not_allowed', $body['code']);
    }
}
