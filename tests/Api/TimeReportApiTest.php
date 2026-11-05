<?php

namespace App\Tests\Api;

use App\Entity\AbsenceRequest;
use App\Tests\Support\ApiTestCase;

final class TimeReportApiTest extends ApiTestCase
{
    public function testReportContainsIstSollAndFractions(): void
    {
        $me       = $this->actAs($this->factory->user(dailyWorkMinutes: 480));
        $vacation = $this->factory->absenceType('report_vacation', name: 'Report vacation');
        // Monday 2025-12-22: 8h worked; Tuesday 23: approved half-day absence; Wednesday 24: half-day holiday
        $this->factory->timeEntry($me, '2025-12-22 08:00', '2025-12-22 16:00');
        $this->factory->absenceRequest($me, $vacation, '2025-12-23', status: AbsenceRequest::STATUS_APPROVED, halfDay: true);
        $this->factory->holiday('2025-12-24', [$me], halfDay: true);
        $this->factory->workLocation($me, '2025-12-22', $office = $this->factory->workLocationType());

        $body = $this->api('GET', '/api/v1/users/' . $me->getId() . '/time-report/2025');

        $this->assertStatus(200);
        $data = $body['data'];
        self::assertSame(2025, $data['year']);
        self::assertCount(12, $data['months']);

        $december = $data['months'][11];
        self::assertSame('2025-12', $december['month']);
        $days = array_column($december['days'], null, 'date');

        self::assertTrue($days['2025-12-22']['workday']);
        self::assertSame(8 * 3600, $days['2025-12-22']['istSeconds'] + 0);
        self::assertSame(8 * 3600, $days['2025-12-22']['sollSeconds']);
        self::assertSame($office->getName(), $days['2025-12-22']['workLocation']['name']);
        self::assertSame('2025-12-22T08:00:00+00:00', $days['2025-12-22']['firstStart']);

        self::assertSame(0.5, (float) $days['2025-12-23']['absenceFraction']);
        self::assertSame(4 * 3600, $days['2025-12-23']['sollSeconds']);
        self::assertSame('report_vacation', $days['2025-12-23']['absence']['typeKey']);

        self::assertSame(0.5, (float) $days['2025-12-24']['holidayFraction']);
        self::assertSame(0.5, (float) $days['2025-12-24']['workFraction']);

        self::assertFalse($days['2025-12-27']['workday']);
        self::assertSame(0, $days['2025-12-27']['sollSeconds']);

        self::assertSame($data['istSeconds'] - $data['sollSeconds'], $data['deltaSeconds']);
        self::assertSame(array_sum(array_column($data['months'], 'sollSeconds')), $data['sollSeconds']);
    }

    public function testAbsenceDaysCountLikeTheQuota(): void
    {
        $me       = $this->actAs($this->factory->user());
        $vacation = $this->factory->absenceType();
        // Friday 2025-10-03 is a (full) holiday, the absence runs Friday to Monday
        $this->factory->holiday('2025-10-03', [$me]);
        $this->factory->absenceRequest($me, $vacation, '2025-10-03', '2025-10-06', AbsenceRequest::STATUS_APPROVED);

        $body = $this->api('GET', '/api/v1/users/' . $me->getId() . '/time-report/2025');

        $days = array_column($body['data']['months'][9]['days'], null, 'date');
        foreach (['2025-10-03', '2025-10-04', '2025-10-05', '2025-10-06'] as $date) {
            self::assertSame(1.0, (float) $days[$date]['absenceFraction'], $date);
        }
        $total = array_sum(array_map(static fn(array $d) => (float) $d['absenceDays'], $days));
        self::assertSame(1.0, $total);
        self::assertSame(1.0, (float) $days['2025-10-06']['absenceDays']);
    }

    public function testSollIsCappedToToday(): void
    {
        self::mockTime('2026-11-05 10:00:00'); // a Thursday
        $me = $this->actAs($this->factory->user(dailyWorkMinutes: 480));

        $body = $this->api('GET', '/api/v1/users/' . $me->getId() . '/time-report/2026');

        $november = $body['data']['months'][10];
        $days     = array_column($november['days'], null, 'date');
        self::assertSame(8 * 3600, $days['2026-11-05']['sollSeconds']);
        self::assertSame(0, $days['2026-11-06']['sollSeconds']);
        // 2026-11-02 … 11-05 are four workdays up to today
        self::assertSame(4 * 8 * 3600, $november['sollSeconds']);
        self::assertSame(0, $body['data']['months'][11]['sollSeconds']);
        self::assertTrue($november['editable']);
        self::assertTrue($body['data']['months'][9]['editable']);
        self::assertFalse($body['data']['months'][8]['editable']);

        $future = $this->api('GET', '/api/v1/users/' . $me->getId() . '/time-report/2099');
        self::assertSame(0, $future['data']['sollSeconds']);
    }

    public function testForeignReportIsNotFoundButAdminCanReadIt(): void
    {
        $user = $this->factory->user();

        $this->actAs($this->factory->user());
        $this->api('GET', '/api/v1/users/' . $user->getId() . '/time-report/2025');
        $this->assertProblem(404, 'not_found');

        $this->actAs($this->factory->admin());
        $this->api('GET', '/api/v1/users/' . $user->getId() . '/time-report/2025');
        $this->assertStatus(200);
    }
}
