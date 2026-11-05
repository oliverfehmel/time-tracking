<?php

namespace App\Tests\Api;

use App\Entity\ApiToken;
use App\Entity\TimeEntry;
use App\Tests\Support\ApiTestCase;

final class TimeEntryApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-11-05 10:00:00');
    }

    public function testTimerLifecycle(): void
    {
        $me = $this->actAs($this->factory->user());

        $timer = $this->api('GET', '/api/v1/timer');
        self::assertSame(['running' => false, 'entry' => null], $timer['data']);

        $started = $this->api('POST', '/api/v1/timer/start');
        $this->assertStatus(201);
        self::assertSame('2026-11-05T10:00:00+00:00', $started['data']['startedAt']);
        self::assertTrue($started['data']['running']);
        self::assertNull($started['data']['durationSeconds']);
        self::assertSame($me->getId(), $started['data']['userId']);

        $this->api('POST', '/api/v1/timer/start');
        $this->assertProblem(409, 'timer_already_running');

        $timer = $this->api('GET', '/api/v1/timer');
        self::assertTrue($timer['data']['running']);
        self::assertSame($started['data']['id'], $timer['data']['entry']['id']);

        self::mockTime('2026-11-05 11:30:00');
        $stopped = $this->api('POST', '/api/v1/timer/stop');
        $this->assertStatus(200);
        self::assertSame(5400, $stopped['data']['durationSeconds']);

        $this->api('POST', '/api/v1/timer/stop');
        $this->assertProblem(409, 'no_running_timer');
    }

    public function testListOwnEntriesInRange(): void
    {
        $me    = $this->actAs($this->factory->user());
        $other = $this->factory->user();
        $this->factory->timeEntry($me, '2026-11-03 08:00', '2026-11-03 12:00');
        $this->factory->timeEntry($me, '2026-11-04 08:00', '2026-11-04 12:00');
        $this->factory->timeEntry($me, '2026-10-30 22:00', '2026-11-01 02:00'); // overlaps the range start
        $this->factory->timeEntry($other, '2026-11-03 08:00', '2026-11-03 12:00');

        $all = $this->api('GET', '/api/v1/time-entries');
        self::assertSame(3, $all['meta']['total']);

        $range = $this->api('GET', '/api/v1/time-entries?from=2026-11-01&to=2026-11-03');
        self::assertSame(['2026-10-30T22:00:00+00:00', '2026-11-03T08:00:00+00:00'], array_column($range['data'], 'startedAt'));
        self::assertSame(14400, $range['data'][1]['durationSeconds']);
    }

    public function testRegularUserCannotFilterOtherUsers(): void
    {
        $this->actAs($this->factory->user());
        $other = $this->factory->user();

        $this->api('GET', '/api/v1/time-entries?userId=' . $other->getId());

        $this->assertProblem(403, 'foreign_user');
    }

    public function testAdminReadsEntriesOfAnyUser(): void
    {
        $this->actAs($this->factory->admin());
        $user  = $this->factory->user();
        $entry = $this->factory->timeEntry($user, '2026-11-03 08:00', '2026-11-03 12:00');

        $list = $this->api('GET', '/api/v1/time-entries?userId=' . $user->getId());
        self::assertSame([$entry->getId()], array_column($list['data'], 'id'));

        $this->api('GET', '/api/v1/time-entries?userId=999999');
        $this->assertProblem(404, 'not_found');
    }

    public function testForeignEntryIsNotFound(): void
    {
        $this->actAs($this->factory->user());
        $entry = $this->factory->timeEntry($this->factory->user(), '2026-11-03 08:00', '2026-11-03 12:00');

        $this->api('GET', '/api/v1/time-entries/' . $entry->getId());
        $this->assertProblem(404, 'not_found');

        $this->api('PATCH', '/api/v1/time-entries/' . $entry->getId(), ['stoppedAt' => '2026-11-03T13:00:00+00:00']);
        $this->assertProblem(404, 'not_found');

        $this->api('DELETE', '/api/v1/time-entries/' . $entry->getId());
        $this->assertProblem(404, 'not_found');
    }

    public function testCreateConvertsOffsetsToServerTime(): void
    {
        $me = $this->actAs($this->factory->user());

        $body = $this->api('POST', '/api/v1/time-entries', [
            'startedAt' => '2026-11-04T09:00:00+01:00',
            'stoppedAt' => '2026-11-04T12:30:00+01:00',
        ]);

        $this->assertStatus(201);
        self::assertSame('2026-11-04T08:00:00+00:00', $body['data']['startedAt']);
        self::assertSame(12600, $body['data']['durationSeconds']);
        self::assertSame(['adjusted' => false], $body['meta']);
        self::assertSame($me->getId(), $body['data']['userId']);
    }

    public function testCreateValidation(): void
    {
        $this->actAs($this->factory->user());

        $body = $this->api('POST', '/api/v1/time-entries', ['startedAt' => 'yesterday', 'stoppedAt' => '']);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'startedAt');
        $this->assertViolation($body, 'stoppedAt');

        $body = $this->api('POST', '/api/v1/time-entries', [
            'startedAt' => '2026-11-04T12:00:00+00:00',
            'stoppedAt' => '2026-11-04T12:00:00+00:00',
        ]);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'stoppedAt', 'stopped_before_started');
    }

    public function testOverlapsAreAdjustedOrRejected(): void
    {
        $me = $this->actAs($this->factory->user());
        $this->factory->timeEntry($me, '2026-11-04 08:00', '2026-11-04 10:00');

        $adjusted = $this->api('POST', '/api/v1/time-entries', [
            'startedAt' => '2026-11-04T09:00:00+00:00',
            'stoppedAt' => '2026-11-04T12:00:00+00:00',
        ]);
        $this->assertStatus(201);
        self::assertTrue($adjusted['meta']['adjusted']);
        self::assertSame('2026-11-04T10:00:00+00:00', $adjusted['data']['startedAt']);

        $body = $this->api('POST', '/api/v1/time-entries', [
            'startedAt' => '2026-11-04T08:30:00+00:00',
            'stoppedAt' => '2026-11-04T11:00:00+00:00',
        ]);
        $this->assertProblem(422, 'time_range_fully_overlaps');
        $this->assertViolation($body, 'startedAt', 'time_range_fully_overlaps');
    }

    public function testRegularUserIsBoundToEditLocks(): void
    {
        $this->actAs($this->factory->user());

        $this->api('POST', '/api/v1/time-entries', ['startedAt' => '2026-09-30T08:00:00+00:00', 'stoppedAt' => '2026-09-30T12:00:00+00:00']);
        $this->assertProblem(403, 'month_not_editable');

        $this->api('POST', '/api/v1/time-entries', ['startedAt' => '2026-10-01T08:00:00+00:00', 'stoppedAt' => '2026-10-01T12:00:00+00:00']);
        $this->assertStatus(201);

        $this->factory->settings(['usersAreAllowedToChangeTimeEntries' => false]);
        $this->api('POST', '/api/v1/time-entries', ['startedAt' => '2026-11-02T08:00:00+00:00', 'stoppedAt' => '2026-11-02T12:00:00+00:00']);
        $this->assertProblem(403, 'time_editing_disabled');
    }

    public function testAdminWritesForOthersWithoutLocks(): void
    {
        $this->actAs($this->factory->admin());
        $user = $this->factory->user();
        $this->factory->settings(['usersAreAllowedToChangeTimeEntries' => false]);

        $body = $this->api('POST', '/api/v1/time-entries', [
            'startedAt' => '2026-06-01T08:00:00+00:00',
            'stoppedAt' => '2026-06-01T12:00:00+00:00',
            'userId'    => $user->getId(),
        ]);

        $this->assertStatus(201);
        self::assertSame($user->getId(), $body['data']['userId']);

        $this->api('PATCH', '/api/v1/time-entries/' . $body['data']['id'], ['stoppedAt' => '2026-06-01T13:00:00+00:00']);
        $this->assertStatus(200);

        $this->api('DELETE', '/api/v1/time-entries/' . $body['data']['id']);
        $this->assertStatus(204);
    }

    public function testRegularUserCannotWriteForOthers(): void
    {
        $this->actAs($this->factory->user());

        $this->api('POST', '/api/v1/time-entries', [
            'startedAt' => '2026-11-04T08:00:00+00:00',
            'stoppedAt' => '2026-11-04T12:00:00+00:00',
            'userId'    => $this->factory->user()->getId(),
        ]);

        $this->assertProblem(403, 'foreign_user');
    }

    public function testPatchIsMergePatch(): void
    {
        $me    = $this->actAs($this->factory->user());
        $entry = $this->factory->timeEntry($me, '2026-11-04 08:00', '2026-11-04 12:00');

        $body = $this->api('PATCH', '/api/v1/time-entries/' . $entry->getId(), ['stoppedAt' => '2026-11-04T13:15:00+00:00']);

        $this->assertStatus(200);
        self::assertSame('2026-11-04T08:00:00+00:00', $body['data']['startedAt']);
        self::assertSame('2026-11-04T13:15:00+00:00', $body['data']['stoppedAt']);
        self::assertFalse($body['meta']['adjusted']);
    }

    public function testRejectedPatchDoesNotChangeEntry(): void
    {
        $me    = $this->actAs($this->factory->user());
        $entry = $this->factory->timeEntry($me, '2026-11-04 08:00', '2026-11-04 12:00');

        $this->api('PATCH', '/api/v1/time-entries/' . $entry->getId(), ['startedAt' => '2026-08-04T08:00:00+00:00', 'stoppedAt' => '2026-08-04T12:00:00+00:00']);

        $this->assertProblem(403, 'month_not_editable');
        self::assertSame('2026-11-04 08:00', $this->reload($entry)->getStartedAt()->format('Y-m-d H:i'));
    }

    public function testRunningEntryCanOnlyBeChangedViaTimer(): void
    {
        $me    = $this->actAs($this->factory->user());
        $entry = $this->factory->timeEntry($me, '2026-11-05 08:00', null);

        $this->api('PATCH', '/api/v1/time-entries/' . $entry->getId(), ['startedAt' => '2026-11-05T07:00:00+00:00']);
        $this->assertProblem(409, 'running_entry');

        $this->api('DELETE', '/api/v1/time-entries/' . $entry->getId());
        $this->assertStatus(204);
        self::assertNull($this->em->getRepository(TimeEntry::class)->findOneBy(['user' => $me]));
    }

    public function testReadOnlyTokenCanReadButNotWrite(): void
    {
        $me = $this->actAs($this->factory->user(), ApiToken::SCOPE_READ);
        $entry = $this->factory->timeEntry($me, '2026-11-04 08:00', '2026-11-04 12:00');

        $this->api('GET', '/api/v1/time-entries/' . $entry->getId());
        $this->assertStatus(200);

        $this->api('DELETE', '/api/v1/time-entries/' . $entry->getId());
        $this->assertProblem(403, 'read_only_token');
    }
}
