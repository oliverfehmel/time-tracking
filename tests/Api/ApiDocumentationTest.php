<?php

namespace App\Tests\Api;

use App\Tests\Support\FunctionalTestCase;
use App\Tests\Support\OpenApiContract;
use Symfony\Component\Routing\RouterInterface;

final class ApiDocumentationTest extends FunctionalTestCase
{
    public function testEveryApiRouteIsDocumented(): void
    {
        $spec   = OpenApiContract::spec(static::getContainer());
        $routes = static::getContainer()->get(RouterInterface::class)->getRouteCollection();
        $count  = 0;

        foreach ($routes as $name => $route) {
            if (!str_starts_with($route->getPath(), '/api/v1')) {
                continue;
            }

            $path = preg_replace('/\{(\w+)(<[^>]+>)?\}/', '{$1}', $route->getPath());
            self::assertArrayHasKey($path, $spec['paths'], "Route $name ($path) is not documented");
            self::assertNotEmpty($route->getMethods(), "Route $name must restrict its HTTP methods");

            foreach ($route->getMethods() as $method) {
                $operation = $spec['paths'][$path][strtolower($method)] ?? null;
                self::assertNotNull($operation, "$method $path is not documented");
                self::assertNotEmpty($operation['summary'] ?? '', "$method $path has no summary");
                self::assertNotEmpty($operation['tags'] ?? [], "$method $path has no tag");
                self::assertArrayHasKey('401', $operation['responses'], "$method $path does not document 401");
                $count++;
            }
        }

        self::assertGreaterThanOrEqual(35, $count);
    }

    public function testDocumentDeclaresBearerAuthAndSharedSchemas(): void
    {
        $spec = OpenApiContract::spec(static::getContainer());

        self::assertSame('Time Tracking API', $spec['info']['title']);
        self::assertSame(['bearerAuth' => []], $spec['security'][0]);
        self::assertSame('bearer', $spec['components']['securitySchemes']['bearerAuth']['scheme']);
        foreach (['Problem', 'Violation', 'PaginationMeta', 'TimeEntryView', 'AbsenceRequestView', 'HolidayView', 'UserView'] as $schema) {
            self::assertArrayHasKey($schema, $spec['components']['schemas']);
        }
    }

    public function testSwaggerUiAndJsonRequireLogin(): void
    {
        $this->client->request('GET', '/api/docs');
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/api/docs.json');
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/api');
        self::assertResponseRedirects('/login');
    }

    public function testSwaggerUiUsesLocalAssets(): void
    {
        $this->loginAs($this->factory->user());

        $this->client->request('GET', '/api/docs');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        // Served through AssetMapper (compiled by asset-map:compile on deploy)
        self::assertMatchesRegularExpression('#src="/assets/bundles/nelmioapidoc/swagger-ui/swagger-ui-bundle[^"]*\.js"#', $html);
        self::assertStringNotContainsString('cdn.jsdelivr.net', $html);
        self::assertStringNotContainsString('unpkg.com', $html);
        self::assertStringContainsString('href="/api"', $html);
    }

    public function testOpenApiJsonIsServed(): void
    {
        $this->loginAs($this->factory->user());

        $this->client->request('GET', '/api/docs.json');

        self::assertResponseIsSuccessful();
        $json = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('/api/v1/time-entries', $json['paths']);
    }

    public function testGuidePageExplainsApiAndLinksSwagger(): void
    {
        $this->loginAs($this->factory->user());

        $crawler = $this->client->request('GET', '/api', server: ['HTTP_HOST' => 'tt.example.test']);

        self::assertResponseIsSuccessful();
        self::assertSame('/api/docs', $crawler->filter('[data-testid="swagger-link"]')->attr('href'));
        self::assertSelectorTextContains('main', 'http://tt.example.test/api/v1/me');
        self::assertSelectorTextContains('main', 'Halbe Tage sind deaktiviert');
        self::assertSelectorExists('a.nav-link.active[href="/api"]');

        $this->factory->settings(['halfDaysEnabled' => true]);
        $this->client->request('GET', '/api');
        self::assertSelectorTextContains('main', 'Halbe Tage sind aktiviert');
    }

    public function testGuideIsLinkedInSidebarForEveryone(): void
    {
        $this->loginAs($this->factory->user());

        $crawler = $this->client->request('GET', '/');

        self::assertCount(1, $crawler->filter('aside a[href="/api"]'));
    }
}
