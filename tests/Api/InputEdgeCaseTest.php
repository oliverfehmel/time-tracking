<?php

namespace App\Tests\Api;

use App\Entity\AbsenceQuota;
use App\Entity\AbsenceRequest;
use App\Entity\TimeEntry;
use App\Tests\Support\ApiTestCase;

/**
 * Second review round: empty optional values, path parameters, body formats, duration limits.
 */
final class InputEdgeCaseTest extends ApiTestCase
{
    public function testEmptyEndDateIsRejectedInsteadOfBecomingToday(): void
    {
        $this->actAs($this->factory->user());
        $type = $this->factory->absenceType();

        $body = $this->api('POST', '/api/v1/absence-requests', ['typeId' => $type->getId(), 'startDate' => '2030-01-07', 'endDate' => '']);

        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'endDate');
        self::assertSame(0, $this->em->getRepository(AbsenceRequest::class)->count([]));
        self::assertQueuedEmailCount(0);
    }

    public function testNullEndDateMeansSingleDay(): void
    {
        $this->actAs($this->factory->user());
        $type = $this->factory->absenceType();

        $body = $this->api('POST', '/api/v1/absence-requests', ['typeId' => $type->getId(), 'startDate' => '2030-01-07', 'endDate' => null]);

        $this->assertStatus(201);
        self::assertSame('2030-01-07', $body['data']['endDate']);
    }

    public function testEmptyDateFiltersAreRejectedInsteadOfFilteringOnToday(): void
    {
        $me = $this->actAs($this->factory->user());

        $cases = [
            '/api/v1/time-entries?from='                               => 'from',
            '/api/v1/time-entries?to='                                 => 'to',
            '/api/v1/holidays?from='                                   => 'from',
            '/api/v1/users/' . $me->getId() . '/work-locations?to='    => 'to',
        ];
        foreach ($cases as $uri => $field) {
            $body = $this->api('GET', $uri);
            $this->assertProblem(422, 'validation_failed');
            $this->assertViolation($body, $field);
        }
    }

    public function testInvalidDatesInQueryAreRejected(): void
    {
        $this->actAs($this->factory->user());

        $body = $this->api('GET', '/api/v1/time-entries?from=2026-02-30');

        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'from');
    }

    public function testEntriesLongerThanADayAreRejected(): void
    {
        self::mockTime('2026-11-05 10:00:00');
        $this->actAs($this->factory->user());

        $body = $this->api('POST', '/api/v1/time-entries', ['startedAt' => '2026-10-01T08:00:00Z', 'stoppedAt' => '2027-10-01T08:00:00Z']);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'stoppedAt', 'entry_too_long');

        // Exactly 24 hours (e.g. a night shift spanning two days) is still possible
        $this->api('POST', '/api/v1/time-entries', ['startedAt' => '2026-10-01T08:00:00Z', 'stoppedAt' => '2026-10-02T08:00:00Z']);
        $this->assertStatus(201);
        self::assertSame(1, $this->em->getRepository(TimeEntry::class)->count([]));
    }

    public function testYearOutsideRangeIsNotARoute(): void
    {
        $this->actAs($this->factory->admin());
        $user = $this->factory->user();
        $type = $this->factory->absenceType(requiresQuota: true);

        $this->api('PUT', sprintf('/api/v1/users/%d/absence-quotas/0000/%d', $user->getId(), $type->getId()), ['quotaDays' => 5]);
        $this->assertProblem(404, 'not_found');
        $this->api('GET', sprintf('/api/v1/users/%d/time-report/0001', $user->getId()));
        $this->assertProblem(404, 'not_found');

        self::assertSame(0, $this->em->getRepository(AbsenceQuota::class)->count(['user' => $user->getId()]));
    }

    public function testImpossibleDateInWorkLocationPathIsValidationError(): void
    {
        $me = $this->actAs($this->factory->user());
        $type = $this->factory->workLocationType();

        $body = $this->api('PUT', '/api/v1/users/' . $me->getId() . '/work-locations/2026-11-31', ['locationTypeId' => $type->getId()]);

        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'date');
    }

    public function testFormEncodedBodiesAreRejectedOnPostToo(): void
    {
        $admin   = $this->actAs($this->factory->admin());
        $request = $this->factory->absenceRequest($this->factory->user(), $this->factory->absenceType(), '2030-03-04');

        $this->apiRaw('POST', '/api/v1/absence-requests/' . $request->getId() . '/reject', 'reason=x', 'application/x-www-form-urlencoded');

        $this->assertProblem(415, 'unsupported_media_type');
        self::assertSame(AbsenceRequest::STATUS_PENDING, $this->reload($request)->getStatus());
        self::assertNotNull($admin->getId());
    }
}
