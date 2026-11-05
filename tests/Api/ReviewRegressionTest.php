<?php

namespace App\Tests\Api;

use App\Entity\TimeEntry;
use App\Tests\Support\ApiTestCase;

/**
 * Edge cases around input handling: merge patch validation, whitespace, timestamp formats, paging limits, language.
 */
final class ReviewRegressionTest extends ApiTestCase
{
    public function testPatchDoesNotRevalidateUntouchedLegacyValues(): void
    {
        $this->actAs($this->factory->admin());
        // Created through the UI / console, where neither the key format nor the email format is enforced
        $type = $this->factory->absenceType('Home Office');
        $user = $this->factory->user('ops@localhost');

        $body = $this->api('PATCH', '/api/v1/absence-types/' . $type->getId(), ['active' => false]);
        $this->assertStatus(200);
        self::assertFalse($body['data']['active']);
        self::assertSame('Home Office', $body['data']['key']);

        $this->api('PATCH', '/api/v1/users/' . $user->getId(), ['dailyWorkMinutes' => 300]);
        $this->assertStatus(200);

        // Sent fields are still validated
        $body = $this->api('PATCH', '/api/v1/absence-types/' . $type->getId(), ['key' => 'Still Bad']);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'key');
    }

    public function testPatchStillChecksCrossFieldRules(): void
    {
        $me    = $this->actAs($this->factory->user());
        $now   = new \DateTimeImmutable('today');
        $entry = $this->factory->timeEntry($me, $now->format('Y-m-d') . ' 08:00', $now->format('Y-m-d') . ' 12:00');

        // Only startedAt is sent, but it would end up after stoppedAt
        $body = $this->api('PATCH', '/api/v1/time-entries/' . $entry->getId(), ['startedAt' => $now->format('Y-m-d') . 'T13:00:00+00:00']);

        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'stoppedAt', 'stopped_before_started');
    }

    public function testPatchTypeErrorsAreTranslatedLikePost(): void
    {
        $this->actAs($this->factory->admin());
        $user = $this->factory->user();

        $patch = $this->api('PATCH', '/api/v1/users/' . $user->getId(), ['dailyWorkMinutes' => 'eight'], ['Accept-Language' => 'de']);
        $this->assertProblem(422, 'validation_failed');
        $post = $this->api('POST', '/api/v1/users', ['email' => 'x@example.test', 'name' => 'X', 'password' => 'secret123', 'dailyWorkMinutes' => 'eight'], ['Accept-Language' => 'de']);
        $this->assertProblem(422, 'validation_failed');

        self::assertSame($post['violations'][0]['message'], $patch['violations'][0]['message']);
        self::assertSame($post['violations'][0]['code'], $patch['violations'][0]['code']);
        self::assertStringNotContainsString('This value', $patch['violations'][0]['message']);
    }

    public function testWhitespaceOnlyNamesAreRejected(): void
    {
        $this->actAs($this->factory->admin());

        $body = $this->api('POST', '/api/v1/holidays', ['name' => '   ', 'date' => '2030-12-31']);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'name');

        $body = $this->api('POST', '/api/v1/absence-types', ['name' => '  ', 'key' => 'ws_type']);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'name');

        $body = $this->api('POST', '/api/v1/users', ['email' => 'ws@example.test', 'name' => ' ', 'password' => 'secret123']);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'name');
    }

    public function testFractionalSecondsAndZuluTimeAreAccepted(): void
    {
        self::mockTime('2026-11-05 10:00:00');
        $this->actAs($this->factory->user());

        $body = $this->api('POST', '/api/v1/time-entries', [
            'startedAt' => '2026-11-03T08:00:00.123Z',
            'stoppedAt' => '2026-11-03T09:30:00.999999+01:00',
        ]);

        $this->assertStatus(201);
        self::assertSame('2026-11-03T08:00:00+00:00', $body['data']['startedAt']);
        self::assertSame('2026-11-03T08:30:00+00:00', $body['data']['stoppedAt']);
        self::assertSame(1800, $body['data']['durationSeconds']);

        foreach (['2026-11-03 08:00:00', '2026-11-03T08:00:00', '2026-13-03T08:00:00Z', '2026-11-03T08:00:00.Z'] as $invalid) {
            $body = $this->api('POST', '/api/v1/time-entries', ['startedAt' => $invalid, 'stoppedAt' => '2026-11-03T10:00:00Z']);
            $this->assertProblem(422, 'validation_failed');
            $this->assertViolation($body, 'startedAt');
        }
        self::assertCount(1, $this->em->getRepository(TimeEntry::class)->findAll());
    }

    public function testHugePageIsValidationErrorNotServerError(): void
    {
        $this->actAs($this->factory->user());

        $body = $this->api('GET', '/api/v1/users?page=9223372036854775807');

        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'page');
    }
}
