<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class AbsenceTypeApiTest extends ApiTestCase
{
    public function testRegularUserSeesActiveTypesOnly(): void
    {
        $this->actAs($this->factory->user());
        $active   = $this->factory->absenceType(name: 'AAA active');
        $inactive = $this->factory->absenceType(active: false, name: 'AAA inactive');

        $list = $this->api('GET', '/api/v1/absence-types?perPage=200');
        $ids  = array_column($list['data'], 'id');
        self::assertContains($active->getId(), $ids);
        self::assertNotContains($inactive->getId(), $ids);

        $this->api('GET', '/api/v1/absence-types/' . $inactive->getId());
        $this->assertProblem(404, 'not_found');
    }

    public function testAdminSeesInactiveTypes(): void
    {
        $this->actAs($this->factory->admin());
        $inactive = $this->factory->absenceType(active: false);

        $body = $this->api('GET', '/api/v1/absence-types/' . $inactive->getId());

        self::assertFalse($body['data']['active']);
    }

    public function testAdminCreatesAndPatchesType(): void
    {
        $this->actAs($this->factory->admin());

        $created = $this->api('POST', '/api/v1/absence-types', [
            'name' => 'Education', 'key' => 'education', 'requiresQuota' => true, 'defaultYearlyQuotaDays' => 5,
        ]);
        $this->assertStatus(201);
        self::assertSame(5.0, (float) $created['data']['defaultYearlyQuotaDays']);
        self::assertTrue($created['data']['requiresApproval']);
        self::assertFalse($created['data']['allowOverLimit']);

        $patched = $this->api('PATCH', '/api/v1/absence-types/' . $created['data']['id'], ['active' => false, 'defaultYearlyQuotaDays' => null]);
        self::assertFalse($patched['data']['active']);
        self::assertNull($patched['data']['defaultYearlyQuotaDays']);
        self::assertSame('education', $patched['data']['key']);
    }

    public function testValidation(): void
    {
        $this->actAs($this->factory->admin());
        $this->factory->absenceType('taken');

        $body = $this->api('POST', '/api/v1/absence-types', ['name' => '', 'key' => 'Not A Slug']);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'name');
        $this->assertViolation($body, 'key');

        $this->api('POST', '/api/v1/absence-types', ['name' => 'Other', 'key' => 'taken']);
        $this->assertProblem(409, 'duplicate');

        $body = $this->api('POST', '/api/v1/absence-types', ['name' => 'Too much', 'key' => 'too_much', 'defaultYearlyQuotaDays' => 400]);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'defaultYearlyQuotaDays');
    }

    public function testQuotaStepsFollowHalfDayFeature(): void
    {
        $this->actAs($this->factory->admin());

        $body = $this->api('POST', '/api/v1/absence-types', ['name' => 'Half', 'key' => 'half', 'defaultYearlyQuotaDays' => 2.5]);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'defaultYearlyQuotaDays');

        $this->factory->settings(['halfDaysEnabled' => true]);
        $body = $this->api('POST', '/api/v1/absence-types', ['name' => 'Half', 'key' => 'half', 'defaultYearlyQuotaDays' => 2.5]);
        $this->assertStatus(201);
        self::assertSame(2.5, (float) $body['data']['defaultYearlyQuotaDays']);

        $body = $this->api('POST', '/api/v1/absence-types', ['name' => 'Quarter', 'key' => 'quarter', 'defaultYearlyQuotaDays' => 2.25]);
        $this->assertProblem(422, 'validation_failed');
    }

    public function testStoredHalfValueStaysValidWhenFeatureIsOff(): void
    {
        $this->actAs($this->factory->admin());
        $type = $this->factory->absenceType(requiresQuota: true, defaultYearlyQuotaDays: 27.5);

        $body = $this->api('PATCH', '/api/v1/absence-types/' . $type->getId(), ['name' => 'Renamed']);

        $this->assertStatus(200);
        self::assertSame(27.5, (float) $body['data']['defaultYearlyQuotaDays']);
    }

    public function testRegularUserCannotManageTypes(): void
    {
        $this->actAs($this->factory->user());
        $type = $this->factory->absenceType();

        $this->api('POST', '/api/v1/absence-types', ['name' => 'X', 'key' => 'x']);
        $this->assertProblem(403, 'forbidden');

        $this->api('PATCH', '/api/v1/absence-types/' . $type->getId(), ['name' => 'Y']);
        $this->assertProblem(403, 'forbidden');
    }
}
