<?php

namespace App\Tests\EventListener;

use App\Api\ApiProblem;
use App\Entity\ApiToken;
use App\Entity\User;
use App\EventListener\ApiRequestListener;
use App\Security\ApiTokenHandler;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiRequestListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|null, string}>
     */
    public static function locales(): iterable
    {
        yield 'no header, default en'      => ['en', null, 'en'];
        yield 'no header, default de'      => ['de', null, 'de'];
        yield 'german requested'           => ['en', 'de-DE,de;q=0.9', 'de'];
        yield 'english requested'          => ['de', 'en-US', 'en'];
        yield 'unsupported, default en'    => ['en', 'fr-FR', 'en'];
        yield 'unsupported, default de'    => ['de', 'fr-FR', 'de'];
        yield 'wildcard uses default'      => ['en', '*', 'en'];
        yield 'second choice is supported' => ['de', 'fr-FR,en;q=0.5', 'en'];
    }

    #[DataProvider('locales')]
    public function testLocaleFromAcceptLanguage(string $default, ?string $header, string $expected): void
    {
        $request = Request::create('/api/v1/me');
        $request->headers->remove('Accept-Language');
        if ($header !== null) {
            $request->headers->set('Accept-Language', $header);
        }

        new ApiRequestListener($default)->setLocale($this->event($request));

        self::assertSame($expected, $request->getLocale());
    }

    public function testLocaleIsOnlySetForApiRequests(): void
    {
        $request = Request::create('/api/docs', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);
        $request->setLocale('de');

        new ApiRequestListener('de')->setLocale($this->event($request));

        self::assertSame('de', $request->getLocale());
    }

    public function testReadOnlyTokenMayOnlyRead(): void
    {
        $listener = new ApiRequestListener('de');
        $token    = new ApiToken(new User(), 'x', str_repeat('a', 64), 'tt_x', ApiToken::SCOPE_READ, new DateTimeImmutable());

        $get = Request::create('/api/v1/time-entries');
        $get->attributes->set(ApiTokenHandler::REQUEST_ATTRIBUTE, $token);
        $listener->enforceTokenScope($this->event($get));

        $post = Request::create('/api/v1/time-entries', 'POST');
        $post->attributes->set(ApiTokenHandler::REQUEST_ATTRIBUTE, $token);

        $this->expectException(ApiProblem::class);
        $listener->enforceTokenScope($this->event($post));
    }

    private function event(Request $request): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
