<?php

namespace App\Tests\Api;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Tests\Support\ApiTestCase;

final class AbsenceRequestApiTest extends ApiTestCase
{
    private AbsenceType $vacation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vacation = $this->factory->absenceType('api_vacation', requiresQuota: true, defaultYearlyQuotaDays: 10, name: 'API vacation');
    }

    public function testCreateRequestNotifiesAdmins(): void
    {
        $me = $this->actAs($this->factory->user());
        $this->factory->admin();

        $body = $this->api('POST', '/api/v1/absence-requests', [
            'typeId' => $this->vacation->getId(), 'startDate' => '2030-03-04', 'endDate' => '2030-03-08', 'comment' => ' Trip ',
        ]);

        $this->assertStatus(201);
        self::assertQueuedEmailCount(1);
        self::assertSame(['notificationSent' => true], $body['meta']);
        $data = $body['data'];
        self::assertSame($me->getId(), $data['userId']);
        self::assertSame(['id' => $this->vacation->getId(), 'key' => 'api_vacation', 'name' => 'API vacation'], $data['type']);
        self::assertSame('pending', $data['status']);
        self::assertSame(5.0, (float) $data['days']);
        self::assertSame(1.0, (float) $data['dayFraction']);
        self::assertFalse($data['halfDay']);
        self::assertSame('Trip', $data['comment']);
        self::assertNull($data['decidedBy']);
    }

    public function testEndDateDefaultsToStartDate(): void
    {
        $this->actAs($this->factory->user());

        $body = $this->api('POST', '/api/v1/absence-requests', ['typeId' => $this->vacation->getId(), 'startDate' => '2030-03-04']);

        self::assertSame('2030-03-04', $body['data']['endDate']);
        self::assertSame(1.0, (float) $body['data']['days']);
    }

    public function testTypeWithoutApprovalIsApprovedImmediately(): void
    {
        $this->actAs($this->factory->user());
        $sick = $this->factory->absenceType(requiresApproval: false);

        $body = $this->api('POST', '/api/v1/absence-requests', ['typeId' => $sick->getId(), 'startDate' => '2030-03-04']);

        self::assertSame('approved', $body['data']['status']);
    }

    public function testValidationAndDomainRules(): void
    {
        $me       = $this->actAs($this->factory->user());
        $inactive = $this->factory->absenceType(active: false);
        // Fixtures first: the kernel reboots between requests and detaches entities from the test's entity manager.
        $this->factory->absenceRequest($me, $this->vacation, '2030-04-01', '2030-04-02');

        $body = $this->api('POST', '/api/v1/absence-requests', ['startDate' => '04.03.2030']);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'typeId');
        $this->assertViolation($body, 'startDate');

        $body = $this->api('POST', '/api/v1/absence-requests', ['typeId' => 999999, 'startDate' => '2030-03-04']);
        $this->assertProblem(422, 'unknown_type');
        $this->assertViolation($body, 'typeId', 'unknown_type');

        $this->api('POST', '/api/v1/absence-requests', ['typeId' => $inactive->getId(), 'startDate' => '2030-03-04']);
        $this->assertProblem(422, 'type_inactive');

        $body = $this->api('POST', '/api/v1/absence-requests', ['typeId' => $this->vacation->getId(), 'startDate' => '2030-03-08', 'endDate' => '2030-03-04']);
        $this->assertProblem(422, 'end_before_start');
        $this->assertViolation($body, 'endDate', 'end_before_start');

        $this->api('POST', '/api/v1/absence-requests', ['typeId' => $this->vacation->getId(), 'startDate' => '2030-04-02', 'endDate' => '2030-04-03']);
        $this->assertProblem(422, 'overlaps_existing');

        $body = $this->api('POST', '/api/v1/absence-requests', [
            'typeId' => $this->vacation->getId(), 'startDate' => '2030-05-06', 'endDate' => '2030-05-20',
        ], ['Accept-Language' => 'de-DE']);
        $this->assertProblem(422, 'quota_exceeded');
        self::assertStringContainsString('2 von 10 Tagen', $body['detail']);
    }

    public function testMessagesAreTranslatedFromAcceptLanguage(): void
    {
        $this->actAs($this->factory->user());

        $body = $this->api('POST', '/api/v1/absence-requests', [
            'typeId' => $this->vacation->getId(), 'startDate' => '2030-05-06', 'endDate' => '2030-05-24',
        ], ['Accept-Language' => 'en']);

        $this->assertProblem(422, 'quota_exceeded');
        self::assertSame('The quota is not sufficient: 0 of 10 days are already approved or pending, the new request covers 15 days.', $body['detail']);
    }

    // --- half days ---

    public function testHalfDayRequiresEnabledFeature(): void
    {
        $this->actAs($this->factory->user());

        $body = $this->api('POST', '/api/v1/absence-requests', ['typeId' => $this->vacation->getId(), 'startDate' => '2030-03-04', 'halfDay' => true]);

        $this->assertProblem(422, 'half_days_disabled');
        $this->assertViolation($body, 'halfDay', 'half_days_disabled');
    }

    public function testHalfDayRequestWhenEnabled(): void
    {
        $this->factory->settings(['halfDaysEnabled' => true]);
        $this->actAs($this->factory->user());

        $body = $this->api('POST', '/api/v1/absence-requests', ['typeId' => $this->vacation->getId(), 'startDate' => '2030-03-04', 'halfDay' => true]);

        $this->assertStatus(201);
        self::assertTrue($body['data']['halfDay']);
        self::assertSame(0.5, (float) $body['data']['dayFraction']);
        self::assertSame(0.5, (float) $body['data']['days']);
    }

    public function testHalfDayMustBeSingleDay(): void
    {
        $this->factory->settings(['halfDaysEnabled' => true]);
        $this->actAs($this->factory->user());

        $this->api('POST', '/api/v1/absence-requests', [
            'typeId' => $this->vacation->getId(), 'startDate' => '2030-03-04', 'endDate' => '2030-03-05', 'halfDay' => true,
        ]);

        $this->assertProblem(422, 'half_day_single_day');
    }

    public function testTwoHalfDaysShareADayExceptOnHalfDayHoliday(): void
    {
        $this->factory->settings(['halfDaysEnabled' => true]);
        $me = $this->actAs($this->factory->user());
        $this->factory->holiday('2030-12-24', [$me], halfDay: true);
        $half = fn(string $day) => $this->api('POST', '/api/v1/absence-requests', ['typeId' => $this->vacation->getId(), 'startDate' => $day, 'halfDay' => true]);

        $half('2030-03-04');
        $half('2030-03-04');
        $this->assertStatus(201);
        $half('2030-03-04');
        $this->assertProblem(422, 'overlaps_existing');

        $first = $half('2030-12-24');
        $this->assertStatus(201);
        self::assertSame(0.5, (float) $first['data']['days']);
        $half('2030-12-24');
        $this->assertProblem(422, 'overlaps_existing');
    }

    public function testFullDayOnHalfDayHolidayCountsHalf(): void
    {
        $me = $this->actAs($this->factory->user());
        $this->factory->holiday('2030-12-24', [$me], halfDay: true);

        $body = $this->api('POST', '/api/v1/absence-requests', ['typeId' => $this->vacation->getId(), 'startDate' => '2030-12-23', 'endDate' => '2030-12-24']);

        self::assertSame(1.5, (float) $body['data']['days']);
    }

    public function testHalfDayFitsIntoRemainingHalfQuota(): void
    {
        $this->factory->settings(['halfDaysEnabled' => true]);
        $me   = $this->actAs($this->factory->user());
        $type = $this->factory->absenceType(requiresQuota: true, defaultYearlyQuotaDays: 2.5);
        $this->factory->absenceRequest($me, $type, '2030-03-04', '2030-03-05', AbsenceRequest::STATUS_APPROVED);

        $this->api('POST', '/api/v1/absence-requests', ['typeId' => $type->getId(), 'startDate' => '2030-03-06']);
        $this->assertProblem(422, 'quota_exceeded');

        $this->api('POST', '/api/v1/absence-requests', ['typeId' => $type->getId(), 'startDate' => '2030-03-06', 'halfDay' => true]);
        $this->assertStatus(201);
    }

    public function testExistingHalfDayRequestIsReportedWhenFeatureIsOff(): void
    {
        $me      = $this->actAs($this->factory->user());
        $request = $this->factory->absenceRequest($me, $this->vacation, '2030-03-04', halfDay: true);

        $body = $this->api('GET', '/api/v1/absence-requests/' . $request->getId());

        self::assertTrue($body['data']['halfDay']);
        self::assertSame(0.5, (float) $body['data']['days']);
    }

    // --- listing and access ---

    public function testListFiltersAndScoping(): void
    {
        $me    = $this->actAs($this->factory->user());
        $other = $this->factory->user();
        $a     = $this->factory->absenceRequest($me, $this->vacation, '2030-03-04');
        $b     = $this->factory->absenceRequest($me, $this->vacation, '2031-01-02', status: AbsenceRequest::STATUS_APPROVED);
        $this->factory->absenceRequest($other, $this->vacation, '2030-03-04');

        $all = $this->api('GET', '/api/v1/absence-requests');
        self::assertSame([$b->getId(), $a->getId()], array_column($all['data'], 'id'));

        $year = $this->api('GET', '/api/v1/absence-requests?year=2030');
        self::assertSame([$a->getId()], array_column($year['data'], 'id'));

        $approved = $this->api('GET', '/api/v1/absence-requests?status=approved');
        self::assertSame([$b->getId()], array_column($approved['data'], 'id'));

        $this->api('GET', '/api/v1/absence-requests?status=bogus');
        $this->assertProblem(422, 'validation_failed');

        $this->api('GET', '/api/v1/absence-requests?userId=' . $other->getId());
        $this->assertProblem(403, 'foreign_user');
    }

    public function testAdminSeesAllRequestsAndCanFilterByUser(): void
    {
        $this->actAs($this->factory->admin());
        $u1 = $this->factory->user();
        $u2 = $this->factory->user();
        $r1 = $this->factory->absenceRequest($u1, $this->vacation, '2030-03-04');
        $r2 = $this->factory->absenceRequest($u2, $this->vacation, '2030-03-05');

        $pending = $this->api('GET', '/api/v1/absence-requests?status=pending&year=2030');
        self::assertEqualsCanonicalizing([$r1->getId(), $r2->getId()], array_column($pending['data'], 'id'));

        $one = $this->api('GET', '/api/v1/absence-requests?userId=' . $u1->getId());
        self::assertSame([$r1->getId()], array_column($one['data'], 'id'));
    }

    public function testForeignRequestIsNotFound(): void
    {
        $this->actAs($this->factory->user());
        $foreign = $this->factory->absenceRequest($this->factory->user(), $this->vacation, '2030-03-04');

        $this->api('GET', '/api/v1/absence-requests/' . $foreign->getId());
        $this->assertProblem(404, 'not_found');

        $this->api('POST', '/api/v1/absence-requests/' . $foreign->getId() . '/cancel');
        $this->assertProblem(404, 'not_found');
    }

    public function testAdminCreatesRequestForUser(): void
    {
        $this->actAs($this->factory->admin());
        $user = $this->factory->user();

        $body = $this->api('POST', '/api/v1/absence-requests', ['typeId' => $this->vacation->getId(), 'startDate' => '2030-03-04', 'userId' => $user->getId()]);

        $this->assertStatus(201);
        self::assertSame($user->getId(), $body['data']['userId']);
    }

    public function testRegularUserCannotRequestForOthers(): void
    {
        $this->actAs($this->factory->user());

        $this->api('POST', '/api/v1/absence-requests', [
            'typeId' => $this->vacation->getId(), 'startDate' => '2030-03-04', 'userId' => $this->factory->user()->getId(),
        ]);

        $this->assertProblem(403, 'foreign_user');
    }

    // --- workflow ---

    public function testCancel(): void
    {
        $me       = $this->actAs($this->factory->user());
        $pending  = $this->factory->absenceRequest($me, $this->vacation, '2030-03-04');
        $rejected = $this->factory->absenceRequest($me, $this->vacation, '2030-03-05', status: AbsenceRequest::STATUS_REJECTED);

        $body = $this->api('POST', '/api/v1/absence-requests/' . $pending->getId() . '/cancel');
        self::assertSame('cancelled', $body['data']['status']);

        $this->api('POST', '/api/v1/absence-requests/' . $rejected->getId() . '/cancel');
        $this->assertProblem(409, 'cannot_cancel');
    }

    public function testAdminApprovesAndRejects(): void
    {
        $admin = $this->actAs($this->factory->admin());
        $user  = $this->factory->user();
        $a     = $this->factory->absenceRequest($user, $this->vacation, '2030-03-04');
        $b     = $this->factory->absenceRequest($user, $this->vacation, '2030-03-05');

        $approved = $this->api('POST', '/api/v1/absence-requests/' . $a->getId() . '/approve');
        self::assertSame('approved', $approved['data']['status']);
        self::assertSame($admin->getId(), $approved['data']['decidedBy']);
        self::assertNotNull($approved['data']['decidedAt']);
        self::assertSame(['notificationSent' => true], $approved['meta']);
        self::assertQueuedEmailCount(1);

        $body = $this->api('POST', '/api/v1/absence-requests/' . $b->getId() . '/reject', ['reason' => '  ']);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'reason');

        $rejected = $this->api('POST', '/api/v1/absence-requests/' . $b->getId() . '/reject', ['reason' => 'Team event']);
        self::assertSame('rejected', $rejected['data']['status']);
        self::assertSame('Team event', $rejected['data']['rejectReason']);

        $this->api('POST', '/api/v1/absence-requests/' . $a->getId() . '/approve');
        $this->assertProblem(409, 'not_pending');
    }

    public function testAdminCannotDecideOwnRequest(): void
    {
        $admin = $this->actAs($this->factory->admin());
        $own   = $this->factory->absenceRequest($admin, $this->vacation, '2030-03-04');

        $this->api('POST', '/api/v1/absence-requests/' . $own->getId() . '/approve');

        $this->assertProblem(403, 'own_request');
    }

    public function testRegularUserCannotDecide(): void
    {
        $me      = $this->actAs($this->factory->user());
        $request = $this->factory->absenceRequest($me, $this->vacation, '2030-03-04');

        $this->api('POST', '/api/v1/absence-requests/' . $request->getId() . '/approve');
        $this->assertProblem(403, 'forbidden');

        $this->api('POST', '/api/v1/absence-requests/' . $request->getId() . '/reject', ['reason' => 'x']);
        $this->assertProblem(403, 'forbidden');
    }
}
