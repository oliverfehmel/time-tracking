<?php

namespace App\Tests\Controller;

use App\Entity\ApiToken;
use App\Security\ApiTokenManager;
use App\Tests\Support\FunctionalTestCase;

final class ApiTokenControllerTest extends FunctionalTestCase
{
    public function testCreateShowsPlainTokenOnceAndStoresOnlyHash(): void
    {
        self::mockTime('2026-11-05 10:00:00');
        $user = $this->factory->user();
        $this->loginAs($user);

        $crawler = $this->client->request('GET', '/profile/api-tokens');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="api_token_create"]')->form([
            'api_token_create[name]'     => 'CLI script',
            'api_token_create[scope]'    => 'read_write',
            'api_token_create[validity]' => '30',
        ]);
        $crawler = $this->client->submit($form);

        self::assertResponseIsSuccessful();
        // Neither the browser nor Turbo's snapshot cache may keep the page with the plain token
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertCount(1, $crawler->filter('meta[name="turbo-cache-control"][content="no-cache"]'));
        $plain = trim($crawler->filter('[data-testid="new-api-token"] code')->text());
        self::assertMatchesRegularExpression('/^tt_[0-9a-f]{64}$/', $plain);

        $token = $this->em->getRepository(ApiToken::class)->findOneBy(['user' => $user]);
        self::assertSame('CLI script', $token->getName());
        self::assertSame(ApiTokenManager::hash($plain), $token->getTokenHash());
        self::assertSame(substr($plain, 0, 11), $token->getTokenPrefix());
        self::assertTrue($token->allowsWrite());
        self::assertSame('2026-12-05', $token->getExpiresAt()?->format('Y-m-d'));

        // Reloading the page does not show the token again
        $crawler = $this->client->request('GET', '/profile/api-tokens');
        self::assertCount(0, $crawler->filter('[data-testid="new-api-token"]'));
        self::assertCount(0, $crawler->filter('meta[name="turbo-cache-control"]'));
        self::assertStringNotContainsString($plain, (string) $this->client->getResponse()->getContent());
        self::assertSelectorTextContains('table', 'CLI script');
    }

    public function testExpiredTokensAreMarked(): void
    {
        $user = $this->factory->user();
        self::mockTime('2026-01-01 10:00:00');
        static::getContainer()->get(ApiTokenManager::class)->create($user, 'old', ApiToken::SCOPE_READ, 30);
        static::getContainer()->get(ApiTokenManager::class)->create($user, 'forever', ApiToken::SCOPE_READ, null);
        $this->loginAs($user);

        $crawler = $this->client->request('GET', '/profile/api-tokens');

        self::assertCount(1, $crawler->filter('[data-testid="token-expired"]'));
        self::assertStringContainsString('old', $crawler->filter('[data-testid="token-expired"]')->closest('tr')->text());
    }

    public function testNameIsRequired(): void
    {
        $user = $this->factory->user();
        $this->loginAs($user);

        $crawler = $this->client->request('GET', '/profile/api-tokens');
        $this->client->submit($crawler->filter('form[name="api_token_create"]')->form(['api_token_create[name]' => '']));

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->em->getRepository(ApiToken::class)->findOneBy(['user' => $user]));
    }

    public function testUserSeesOnlyOwnTokensAndCanRevokeThem(): void
    {
        $user  = $this->factory->user();
        $other = $this->factory->user();
        [$own]   = static::getContainer()->get(ApiTokenManager::class)->create($user, 'mine', ApiToken::SCOPE_READ, null);
        [$alien] = static::getContainer()->get(ApiTokenManager::class)->create($other, 'theirs', ApiToken::SCOPE_READ, null);
        $ownId   = $own->getId();
        $alienId = $alien->getId();
        $this->loginAs($user);

        $crawler = $this->client->request('GET', '/profile/api-tokens');
        self::assertSelectorTextContains('table', 'mine');
        self::assertSelectorTextNotContains('table', 'theirs');

        $this->client->request('POST', "/profile/api-tokens/$alienId/revoke", ['_token' => 'x']);
        self::assertResponseStatusCodeSame(404);

        $token = $crawler->filter(sprintf('form[action="/profile/api-tokens/%d/revoke"] input[name="_token"]', $ownId))->attr('value');
        $this->client->request('POST', "/profile/api-tokens/$ownId/revoke", ['_token' => $token]);
        self::assertResponseRedirects('/profile/api-tokens');
        $this->assertFlash('success', 'api_token.revoked');

        $this->em->clear();
        self::assertNull($this->em->find(ApiToken::class, $ownId));
        self::assertNotNull($this->em->find(ApiToken::class, $alienId));
    }

    public function testImpersonatingAdminCannotCreateTokens(): void
    {
        $admin = $this->factory->admin();
        $user  = $this->factory->user();
        $this->loginAs($admin);
        $this->client->request('GET', '/?_switch_user=' . urlencode((string) $user->getEmail()));

        $crawler = $this->client->request('GET', '/profile/api-tokens');
        self::assertCount(0, $crawler->filter('form[name="api_token_create"]'));
        self::assertSelectorExists('.alert-warning');

        $this->client->request('POST', '/profile/api-tokens', ['api_token_create' => ['name' => 'sneaky', 'scope' => 'read', 'validity' => 'never']]);
        self::assertResponseRedirects('/profile/api-tokens');
        self::assertNull($this->em->getRepository(ApiToken::class)->findOneBy(['user' => $user]));
    }

    public function testAdminSeesAndRevokesTokensOfUser(): void
    {
        $user    = $this->factory->user();
        [$token] = static::getContainer()->get(ApiTokenManager::class)->create($user, 'laptop', ApiToken::SCOPE_READ_WRITE, 90);
        $tokenId = $token->getId();
        $this->loginAs($this->factory->admin());

        $crawler = $this->client->request('GET', '/admin/users/' . $user->getId() . '/edit');
        self::assertSelectorTextContains('table', 'laptop');

        $action = "/admin/users/api-tokens/$tokenId/revoke";
        $csrf   = $crawler->filter(sprintf('form[action="%s"] input[name="_token"]', $action))->attr('value');
        $this->client->request('POST', $action, ['_token' => $csrf]);

        self::assertResponseRedirects('/admin/users/' . $user->getId() . '/edit');
        $this->em->clear();
        self::assertNull($this->em->find(ApiToken::class, $tokenId));
    }

    public function testRegularUserCannotUseAdminRevoke(): void
    {
        $other   = $this->factory->user();
        [$token] = static::getContainer()->get(ApiTokenManager::class)->create($other, 'x', ApiToken::SCOPE_READ, null);
        $this->loginAs($this->factory->user());

        $this->client->request('POST', '/admin/users/api-tokens/' . $token->getId() . '/revoke', ['_token' => 'x']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeletingUserDeletesTokens(): void
    {
        $user    = $this->factory->user();
        [$token] = static::getContainer()->get(ApiTokenManager::class)->create($user, 'x', ApiToken::SCOPE_READ, null);
        $tokenId = $token->getId();

        $this->em->remove($user);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->em->find(ApiToken::class, $tokenId));
    }
}
