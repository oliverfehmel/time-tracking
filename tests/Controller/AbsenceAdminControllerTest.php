<?php

namespace App\Tests\Controller;

use App\Entity\AbsenceRequest;
use App\Tests\Support\FunctionalTestCase;

/**
 * Characterisation tests for the admin approval workflow.
 */
final class AbsenceAdminControllerTest extends FunctionalTestCase
{
    private const string PAGE = '/admin/absence/approvals';

    public function testApproveSendsDecisionMail(): void
    {
        $admin   = $this->factory->admin();
        $request = $this->factory->absenceRequest($this->factory->user(), $this->factory->absenceType(), '2030-03-04');
        $this->loginAs($admin);

        $action = '/admin/absence/' . $request->getId() . '/approve';
        $this->client->request('POST', $action, ['_token' => $this->csrfTokenFor(self::PAGE, $action)]);

        self::assertResponseRedirects(self::PAGE);
        $this->assertFlash('success', 'admin_absence.approved_success');
        self::assertQueuedEmailCount(1);
        $request = $this->reload($request);
        self::assertSame(AbsenceRequest::STATUS_APPROVED, $request->getStatus());
        self::assertSame($admin->getId(), $request->getApprovedBy()?->getId());
    }

    public function testRejectRequiresReason(): void
    {
        $request = $this->factory->absenceRequest($this->factory->user(), $this->factory->absenceType(), '2030-03-04');
        $this->loginAs($this->factory->admin());

        $action = '/admin/absence/' . $request->getId() . '/reject';
        $this->client->request('POST', $action, ['_token' => $this->csrfTokenFor(self::PAGE, $action), 'reason' => '  ']);

        $this->assertFlash('error', 'admin_absence.reason_required');
        self::assertSame(AbsenceRequest::STATUS_PENDING, $this->reload($request)->getStatus());
    }

    public function testRejectWithReason(): void
    {
        $request = $this->factory->absenceRequest($this->factory->user(), $this->factory->absenceType(), '2030-03-04');
        $this->loginAs($this->factory->admin());

        $action = '/admin/absence/' . $request->getId() . '/reject';
        $this->client->request('POST', $action, ['_token' => $this->csrfTokenFor(self::PAGE, $action), 'reason' => 'Team event']);

        $this->assertFlash('success', 'admin_absence.rejected_success');
        self::assertQueuedEmailCount(1);
        $request = $this->reload($request);
        self::assertSame(AbsenceRequest::STATUS_REJECTED, $request->getStatus());
        self::assertSame('Team event', $request->getRejectReason());
    }

    public function testAdminCannotDecideOwnRequest(): void
    {
        $admin   = $this->factory->admin();
        $request = $this->factory->absenceRequest($admin, $this->factory->absenceType(), '2030-03-04');
        $this->loginAs($admin);

        $this->client->request('POST', '/admin/absence/' . $request->getId() . '/approve', ['_token' => 'x']);

        $this->assertFlash('error', 'admin_absence.own_request_error');
        self::assertSame(AbsenceRequest::STATUS_PENDING, $this->reload($request)->getStatus());
    }

    public function testOnlyPendingRequestsCanBeDecided(): void
    {
        $request = $this->factory->absenceRequest(
            $this->factory->user(), $this->factory->absenceType(), '2030-03-04', status: AbsenceRequest::STATUS_CANCELLED,
        );
        $this->loginAs($this->factory->admin());

        $this->client->request('POST', '/admin/absence/' . $request->getId() . '/approve', ['_token' => 'x']);

        $this->assertFlash('error', 'admin_absence.only_pending');
        self::assertSame(AbsenceRequest::STATUS_CANCELLED, $this->reload($request)->getStatus());
    }

    public function testRegularUserCannotAccessApprovals(): void
    {
        $this->loginAs($this->factory->user());

        $this->client->request('GET', self::PAGE);

        self::assertResponseStatusCodeSame(403);
    }
}
