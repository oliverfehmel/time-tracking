<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Tests\Support\ApiTestCase;

final class UserApiTest extends ApiTestCase
{
    public function testRegularUserOnlySeesThemselves(): void
    {
        $me    = $this->actAs($this->factory->user());
        $other = $this->factory->user();

        $list = $this->api('GET', '/api/v1/users');
        $this->assertStatus(200);
        self::assertSame([$me->getId()], array_column($list['data'], 'id'));
        self::assertSame(['page' => 1, 'perPage' => 50, 'total' => 1], $list['meta']);

        $this->api('GET', '/api/v1/users/' . $me->getId());
        $this->assertStatus(200);

        $this->api('GET', '/api/v1/users/' . $other->getId());
        $this->assertProblem(404, 'not_found');
    }

    public function testAdminListsAllUsersPaginated(): void
    {
        $this->actAs($this->factory->admin());
        $this->factory->user(name: 'Aaron');
        $this->factory->user(name: 'Zoe');

        $page1 = $this->api('GET', '/api/v1/users?perPage=2&page=1');
        $page2 = $this->api('GET', '/api/v1/users?perPage=2&page=2');

        self::assertCount(2, $page1['data']);
        self::assertSame(3, $page1['meta']['total']);
        self::assertCount(1, $page2['data']);
        self::assertSame('Aaron', $page1['data'][0]['name']);
    }

    public function testInvalidPaginationIsRejected(): void
    {
        $this->actAs($this->factory->user());

        $body = $this->api('GET', '/api/v1/users?perPage=500');

        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'perPage');
    }

    public function testAdminCreatesUser(): void
    {
        $this->actAs($this->factory->admin());

        $body = $this->api('POST', '/api/v1/users', [
            'email' => 'anna@example.test', 'name' => 'Anna', 'dailyWorkMinutes' => 420, 'admin' => true, 'password' => 'secret123',
        ]);

        $this->assertStatus(201);
        self::assertSame('anna@example.test', $body['data']['email']);
        self::assertTrue($body['data']['admin']);
        self::assertContains('ROLE_ADMIN', $body['data']['roles']);
        self::assertSame(420, $body['data']['dailyWorkMinutes']);
        self::assertArrayNotHasKey('password', $body['data']);

        $user   = $this->em->getRepository(User::class)->find($body['data']['id']);
        $hasher = static::getContainer()->get('security.user_password_hasher');
        self::assertTrue($hasher->isPasswordValid($user, 'secret123'));
    }

    public function testCreateValidation(): void
    {
        $this->actAs($this->factory->admin());

        $body = $this->api('POST', '/api/v1/users', ['email' => 'not-an-email', 'name' => '', 'password' => 'short']);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'email');
        $this->assertViolation($body, 'name');
        $this->assertViolation($body, 'password');

        $body = $this->api('POST', '/api/v1/users', ['email' => 'ok@example.test', 'name' => 'Ok']);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'password');
    }

    public function testDuplicateEmailIsConflict(): void
    {
        $this->actAs($this->factory->admin());
        $this->factory->user('taken@example.test');

        $this->api('POST', '/api/v1/users', ['email' => 'taken@example.test', 'name' => 'X', 'password' => 'secret123']);

        $this->assertProblem(409, 'duplicate');
    }

    public function testMalformedJsonAndWrongContentType(): void
    {
        $auth = 'Bearer ' . $this->tokenFor($this->factory->admin());
        $user = $this->factory->user();

        foreach (['POST' => '/api/v1/users', 'PATCH' => '/api/v1/users/' . $user->getId()] as $method => $uri) {
            $this->client->request($method, $uri, server: ['HTTP_AUTHORIZATION' => $auth, 'CONTENT_TYPE' => 'application/json'], content: '{broken');
            $this->assertProblem(400, 'bad_request');

            $this->client->request($method, $uri, server: ['HTTP_AUTHORIZATION' => $auth, 'CONTENT_TYPE' => 'text/plain'], content: 'name=x');
            $this->assertProblem(415, 'unsupported_media_type');
        }
    }

    public function testAdminPatchesUser(): void
    {
        $this->actAs($this->factory->admin());
        $user = $this->factory->user(name: 'Old', dailyWorkMinutes: 480);

        $body = $this->api('PATCH', '/api/v1/users/' . $user->getId(), ['name' => 'New', 'dailyWorkMinutes' => 240]);

        $this->assertStatus(200);
        self::assertSame('New', $body['data']['name']);
        self::assertSame(240, $body['data']['dailyWorkMinutes']);
        self::assertSame($user->getEmail(), $body['data']['email']);
        self::assertFalse($body['data']['admin']);
    }

    public function testPatchTypeErrorsAreViolations(): void
    {
        $this->actAs($this->factory->admin());
        $user = $this->factory->user();

        $body = $this->api('PATCH', '/api/v1/users/' . $user->getId(), ['dailyWorkMinutes' => 'eight hours']);

        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'dailyWorkMinutes', 'type_error');
    }

    public function testAdminCannotDemoteOrDeleteThemselves(): void
    {
        $admin = $this->actAs($this->factory->admin());

        $this->api('PATCH', '/api/v1/users/' . $admin->getId(), ['admin' => false]);
        $this->assertProblem(409, 'cannot_demote_self');

        $this->api('DELETE', '/api/v1/users/' . $admin->getId());
        $this->assertProblem(409, 'cannot_delete_self');
    }

    public function testAdminDeletesUserButNotWhileReferenced(): void
    {
        $this->actAs($this->factory->admin());
        $free = $this->factory->user();
        $busy = $this->factory->user();
        $this->factory->timeEntry($busy, '2026-11-02 08:00', '2026-11-02 12:00');

        $this->api('DELETE', '/api/v1/users/' . $free->getId());
        $this->assertStatus(204);

        $this->api('DELETE', '/api/v1/users/' . $busy->getId());
        $this->assertProblem(409, 'still_referenced');
    }

    public function testRegularUserCannotManageUsers(): void
    {
        $me = $this->actAs($this->factory->user());

        $this->api('POST', '/api/v1/users', ['email' => 'x@example.test', 'name' => 'X', 'password' => 'secret123']);
        $this->assertProblem(403, 'forbidden');

        $this->api('PATCH', '/api/v1/users/' . $me->getId(), ['admin' => true]);
        $this->assertProblem(403, 'forbidden');

        $this->api('DELETE', '/api/v1/users/' . $me->getId());
        $this->assertProblem(403, 'forbidden');
    }
}
