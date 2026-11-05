<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class WorkLocationApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-11-05 10:00:00');
    }

    public function testTypesForUsersAndAdmins(): void
    {
        $inactive = $this->factory->workLocationType(active: false);

        $this->actAs($this->factory->user());
        $userList = $this->api('GET', '/api/v1/work-location-types?perPage=200');
        self::assertNotContains($inactive->getId(), array_column($userList['data'], 'id'));
        self::assertContains('office', array_column($userList['data'], 'key'));

        $this->actAs($this->factory->admin());
        $adminList = $this->api('GET', '/api/v1/work-location-types?perPage=200');
        self::assertContains($inactive->getId(), array_column($adminList['data'], 'id'));
    }

    public function testSetListAndClearOwnWorkLocation(): void
    {
        $me   = $this->actAs($this->factory->user());
        $type = $this->factory->workLocationType(icon: 'fa-solid fa-house');
        $url  = '/api/v1/users/' . $me->getId() . '/work-locations';

        $body = $this->api('PUT', "$url/2026-11-04", ['locationTypeId' => $type->getId()]);
        $this->assertStatus(200);
        self::assertSame('2026-11-04', $body['data']['date']);
        self::assertSame($type->getId(), $body['data']['locationType']['id']);
        self::assertSame('fa-solid fa-house', $body['data']['locationType']['icon']);

        $list = $this->api('GET', "$url?from=2026-11-01&to=2026-11-30");
        self::assertSame(['2026-11-04'], array_column($list['data'], 'date'));

        $this->api('DELETE', "$url/2026-11-04");
        $this->assertStatus(204);
        $this->api('DELETE', "$url/2026-11-04");
        $this->assertProblem(404, 'not_found');
    }

    public function testRulesForRegularUsers(): void
    {
        $me       = $this->actAs($this->factory->user());
        $inactive = $this->factory->workLocationType(active: false);
        $type     = $this->factory->workLocationType();
        $url      = '/api/v1/users/' . $me->getId() . '/work-locations';

        $this->api('PUT', "$url/2026-11-04", ['locationTypeId' => $inactive->getId()]);
        $this->assertProblem(422, 'work_location_invalid');

        $body = $this->api('PUT', "$url/2026-11-04", []);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'locationTypeId');

        $this->api('PUT', "$url/2026-08-04", ['locationTypeId' => $type->getId()]);
        $this->assertProblem(403, 'month_not_editable');

        $this->api('PUT', "$url/2026-02-30", ['locationTypeId' => $type->getId()]);
        $this->assertProblem(422, 'validation_failed');

        $this->api('PUT', '/api/v1/users/' . $this->factory->user()->getId() . '/work-locations/2026-11-04', ['locationTypeId' => $type->getId()]);
        $this->assertProblem(404, 'not_found');
    }

    public function testAdminSetsWorkLocationOfOthersInLockedMonth(): void
    {
        $this->actAs($this->factory->admin());
        $user = $this->factory->user();
        $type = $this->factory->workLocationType();

        $body = $this->api('PUT', '/api/v1/users/' . $user->getId() . '/work-locations/2026-03-02', ['locationTypeId' => $type->getId()]);

        $this->assertStatus(200);
        self::assertSame($user->getId(), $body['data']['userId']);
    }
}
