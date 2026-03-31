<?php

namespace App\Tests\Entity;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AbsenceRequestTest extends TestCase
{
    private function makeRequest(): AbsenceRequest
    {
        $type = new AbsenceType();
        $type->setName('Urlaub');
        $type->setKeyName('vacation');

        $requester = new User();
        $requester->setName('Test User');
        $requester->setEmail('user@example.com');

        $request = new AbsenceRequest();
        $request->setRequestedBy($requester);
        $request->setType($type);
        $request->setStartDate(new DateTimeImmutable('2024-07-01'));
        $request->setEndDate(new DateTimeImmutable('2024-07-05'));

        return $request;
    }

    private function makeAdmin(): User
    {
        $admin = new User();
        $admin->setName('Admin');
        $admin->setEmail('admin@example.com');
        return $admin;
    }

    public function testInitialStatusIsPending(): void
    {
        $request = $this->makeRequest();

        $this->assertSame(AbsenceRequest::STATUS_PENDING, $request->getStatus());
    }

    public function testApprove_setsStatusToApproved(): void
    {
        $request = $this->makeRequest();
        $admin   = $this->makeAdmin();

        $request->approve($admin);

        $this->assertSame(AbsenceRequest::STATUS_APPROVED, $request->getStatus());
    }

    public function testApprove_setsApprovedBy(): void
    {
        $request = $this->makeRequest();
        $admin   = $this->makeAdmin();

        $request->approve($admin);

        $this->assertSame($admin, $request->getApprovedBy());
    }

    public function testApprove_setsApprovedAt(): void
    {
        $before  = new DateTimeImmutable();
        $request = $this->makeRequest();
        $request->approve($this->makeAdmin());
        $after = new DateTimeImmutable();

        $approvedAt = $request->getApprovedAt();
        $this->assertNotNull($approvedAt);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $approvedAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $approvedAt->getTimestamp());
    }

    public function testApprove_clearsRejectReason(): void
    {
        $request = $this->makeRequest();
        $admin   = $this->makeAdmin();

        $request->reject($admin, 'Not available');
        $request->approve($admin);

        $this->assertNull($request->getRejectReason());
    }

    public function testReject_setsStatusToRejected(): void
    {
        $request = $this->makeRequest();

        $request->reject($this->makeAdmin(), 'Zu viele Kollegen im Urlaub');

        $this->assertSame(AbsenceRequest::STATUS_REJECTED, $request->getStatus());
    }

    public function testReject_storesReason(): void
    {
        $request = $this->makeRequest();
        $reason  = 'Zu viele Kollegen im Urlaub';

        $request->reject($this->makeAdmin(), $reason);

        $this->assertSame($reason, $request->getRejectReason());
    }

    public function testReject_setsApprovedBy(): void
    {
        $request = $this->makeRequest();
        $admin   = $this->makeAdmin();

        $request->reject($admin, 'reason');

        $this->assertSame($admin, $request->getApprovedBy());
    }

    public function testCancel_setsStatusToCancelled(): void
    {
        $request = $this->makeRequest();

        $request->cancel();

        $this->assertSame(AbsenceRequest::STATUS_CANCELLED, $request->getStatus());
    }

    public function testConstructor_setsCreatedAt(): void
    {
        $before  = new DateTimeImmutable();
        $request = $this->makeRequest();
        $after   = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $request->getCreatedAt()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $request->getCreatedAt()->getTimestamp());
    }
}
