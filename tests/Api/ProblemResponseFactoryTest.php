<?php

namespace App\Tests\Api;

use App\Api\ApiDates;
use App\Api\ApiProblem;
use App\Api\ProblemResponseFactory;
use App\Service\DomainRuleViolation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

final class ProblemResponseFactoryTest extends TestCase
{
    private ProblemResponseFactory $factory;

    protected function setUp(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'api.problem.not_found'  => 'Not there.',
            'api.problem.validation_failed' => 'Invalid.',
            'rule.overlap'           => 'Overlaps (%days% days).',
        ], 'en');

        $this->factory = new ProblemResponseFactory($translator);
    }

    public function testCreateBuildsRfc9457Body(): void
    {
        $response = $this->factory->create(404, 'not_found', headers: ['X-Test' => '1']);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('1', $response->headers->get('X-Test'));
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'code' => 'not_found', 'detail' => 'Not there.'],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testDomainRuleViolationBecomesViolation(): void
    {
        $response = $this->factory->fromDomainRuleViolation(
            new DomainRuleViolation('overlaps_existing', 'rule.overlap', ['days' => 1.5], 409, 'startDate'),
        );
        $body = json_decode((string) $response->getContent(), true);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('overlaps_existing', $body['code']);
        self::assertSame('Overlaps (1.5 days).', $body['detail']);
        self::assertSame([['propertyPath' => 'startDate', 'message' => 'Overlaps (1.5 days).', 'code' => 'overlaps_existing']], $body['violations']);
    }

    public function testConstraintViolations(): void
    {
        $list = new ConstraintViolationList([
            new ConstraintViolation('Too long.', null, [], null, 'name', 'x', code: 'abc'),
        ]);

        $body = json_decode((string) $this->factory->fromConstraintViolations($list)->getContent(), true);

        self::assertSame('validation_failed', $body['code']);
        self::assertSame('Invalid.', $body['detail']);
        self::assertSame([['propertyPath' => 'name', 'message' => 'Too long.', 'code' => 'abc']], $body['violations']);
    }

    public function testApiProblem(): void
    {
        $response = $this->factory->fromApiProblem(ApiProblem::validation('reason', 'Required.', 'blank'));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('reason', json_decode((string) $response->getContent(), true)['violations'][0]['propertyPath']);
    }

    public function testApiDatesUseServerTimeZone(): void
    {
        self::assertSame('2026-11-05T08:30:00+00:00', ApiDates::dateTime(ApiDates::parseDateTime('2026-11-05T09:30:00+01:00')));
        self::assertSame('2026-11-05T08:30:00+00:00', ApiDates::parseDateTime('2026-11-05T08:30:00Z')->format(ApiDates::DATE_TIME));
        self::assertSame('2026-11-05 00:00:00', ApiDates::parseDate('2026-11-05')->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-05', ApiDates::date(new DateTimeImmutable('2026-11-05 23:00')));
        self::assertNull(ApiDates::dateTime(null));
    }
}
