<?php

namespace App\Tests\Api;

use App\Entity\Holiday;
use App\Tests\Support\ApiTestCase;

final class HolidayApiTest extends ApiTestCase
{
    public function testRegularUserSeesAssignedHolidaysWithoutUserList(): void
    {
        $me    = $this->actAs($this->factory->user());
        $other = $this->factory->user();
        $mine  = $this->factory->holiday('2030-01-06', [$me, $other], name: 'Epiphany');
        $this->factory->holiday('2030-08-15', [$other], name: 'Assumption');

        $list = $this->api('GET', '/api/v1/holidays');

        self::assertSame([$mine->getId()], array_column($list['data'], 'id'));
        self::assertNull($list['data'][0]['userIds']);
        self::assertSame('2030-01-06', $list['data'][0]['date']);
        self::assertSame(1.0, (float) $list['data'][0]['dayFraction']);
    }

    public function testRegularUserCannotSeeOtherHolidays(): void
    {
        $this->actAs($this->factory->user());
        $foreign = $this->factory->holiday('2030-08-15', [$this->factory->user()]);

        $this->api('GET', '/api/v1/holidays/' . $foreign->getId());
        $this->assertProblem(404, 'not_found');

        $this->api('GET', '/api/v1/holidays?userId=' . $this->factory->user()->getId());
        $this->assertProblem(403, 'foreign_user');
    }

    public function testAdminListsAllWithFilters(): void
    {
        $this->actAs($this->factory->admin());
        $user = $this->factory->user();
        $a    = $this->factory->holiday('2030-01-06', [$user]);
        $b    = $this->factory->holiday('2030-08-15', []);

        $all = $this->api('GET', '/api/v1/holidays?from=2030-01-01&to=2030-12-31');
        self::assertSame([$a->getId(), $b->getId()], array_column($all['data'], 'id'));
        self::assertSame([$user->getId()], $all['data'][0]['userIds']);

        $forUser = $this->api('GET', '/api/v1/holidays?userId=' . $user->getId());
        self::assertSame([$a->getId()], array_column($forUser['data'], 'id'));

        $range = $this->api('GET', '/api/v1/holidays?from=2030-08-01&to=2030-08-31');
        self::assertSame([$b->getId()], array_column($range['data'], 'id'));
    }

    public function testAdminCreatesUpdatesAndDeletes(): void
    {
        $this->actAs($this->factory->admin());
        $u1 = $this->factory->user();
        $u2 = $this->factory->user();

        $created = $this->api('POST', '/api/v1/holidays', ['name' => ' Carnival ', 'date' => '2030-03-04', 'userIds' => [$u1->getId()]]);
        $this->assertStatus(201);
        self::assertSame('Carnival', $created['data']['name']);
        self::assertFalse($created['data']['halfDay']);
        $id = $created['data']['id'];

        $updated = $this->api('PATCH', "/api/v1/holidays/$id", ['userIds' => [$u2->getId()]]);
        self::assertSame([$u2->getId()], $updated['data']['userIds']);
        self::assertSame('Carnival', $updated['data']['name']);

        $this->api('DELETE', "/api/v1/holidays/$id");
        $this->assertStatus(204);
        $this->em->clear();
        self::assertNull($this->em->find(Holiday::class, $id));
    }

    public function testValidationAndConflicts(): void
    {
        $this->actAs($this->factory->admin());
        $this->factory->holiday('2030-12-25', [], name: 'Christmas');

        $body = $this->api('POST', '/api/v1/holidays', ['name' => '', 'date' => '25.12.2030']);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'name');
        $this->assertViolation($body, 'date');

        $body = $this->api('POST', '/api/v1/holidays', ['name' => 'X', 'date' => '2030-12-26', 'userIds' => [999999]]);
        $this->assertProblem(422, 'unknown_user');
        $this->assertViolation($body, 'userIds', 'unknown_user');

        $this->api('POST', '/api/v1/holidays', ['name' => 'Christmas', 'date' => '2030-12-25']);
        $this->assertProblem(409, 'duplicate');
    }

    public function testRegularUserCannotManageHolidays(): void
    {
        $me      = $this->actAs($this->factory->user());
        $holiday = $this->factory->holiday('2030-01-06', [$me]);

        $this->api('POST', '/api/v1/holidays', ['name' => 'X', 'date' => '2030-12-26']);
        $this->assertProblem(403, 'forbidden');

        $this->api('PATCH', '/api/v1/holidays/' . $holiday->getId(), ['name' => 'Y']);
        $this->assertProblem(403, 'forbidden');

        $this->api('DELETE', '/api/v1/holidays/' . $holiday->getId());
        $this->assertProblem(403, 'forbidden');
    }

    // --- half days ---

    public function testHalfDayHolidayNeedsEnabledFeature(): void
    {
        $this->actAs($this->factory->admin());

        $body = $this->api('POST', '/api/v1/holidays', ['name' => 'Christmas Eve', 'date' => '2030-12-24', 'halfDay' => true]);

        $this->assertProblem(422, 'half_days_disabled');
        $this->assertViolation($body, 'halfDay');
    }

    public function testHalfDayHolidayWhenEnabled(): void
    {
        $this->factory->settings(['halfDaysEnabled' => true]);
        $this->actAs($this->factory->admin());

        $body = $this->api('POST', '/api/v1/holidays', ['name' => 'Christmas Eve', 'date' => '2030-12-24', 'halfDay' => true]);

        $this->assertStatus(201);
        self::assertTrue($body['data']['halfDay']);
        self::assertSame(0.5, (float) $body['data']['dayFraction']);
    }

    public function testExistingHalfDayHolidayKeepsFlagWhenFeatureIsOff(): void
    {
        $this->actAs($this->factory->admin());
        $holiday = $this->factory->holiday('2030-12-24', [], halfDay: true, name: 'Christmas Eve');
        $plain   = $this->factory->holiday('2030-12-31', [], name: 'New Year\'s Eve');

        // PATCH without halfDay keeps the flag
        $body = $this->api('PATCH', '/api/v1/holidays/' . $holiday->getId(), ['name' => 'Heiligabend']);
        self::assertTrue($body['data']['halfDay']);

        // Sending the existing flag again is allowed, switching another holiday to half day is not
        $this->api('PATCH', '/api/v1/holidays/' . $holiday->getId(), ['halfDay' => true]);
        $this->assertStatus(200);
        $this->api('PATCH', '/api/v1/holidays/' . $plain->getId(), ['halfDay' => true]);
        $this->assertProblem(422, 'half_days_disabled');

        // Switching it off is always possible
        $body = $this->api('PATCH', '/api/v1/holidays/' . $holiday->getId(), ['halfDay' => false]);
        self::assertFalse($body['data']['halfDay']);
    }
}
