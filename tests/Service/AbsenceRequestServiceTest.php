<?php

namespace App\Tests\Service;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\User;
use App\Service\AbsenceRequestService;
use App\Service\DomainRuleViolation;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;

final class AbsenceRequestServiceTest extends DatabaseTestCase
{
    private AbsenceRequestService $service;
    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory->settings(['halfDaysEnabled' => true]);
        $this->service = $this->service(AbsenceRequestService::class);
        $this->user    = $this->factory->user();
        $this->admin   = $this->factory->admin();
    }

    public function testCreatePendingRequestAndNotifyAdmins(): void
    {
        $request = $this->newRequest($this->factory->absenceType(), '2030-03-04', '2030-03-08');

        self::assertTrue($this->service->create($request));
        self::assertNotNull($request->getId());
        self::assertSame(AbsenceRequest::STATUS_PENDING, $request->getStatus());
        self::assertCount(1, $this->queuedMails());
    }

    public function testTypeWithoutApprovalIsApproved(): void
    {
        $request = $this->newRequest($this->factory->absenceType(requiresApproval: false), '2030-03-04');

        $this->service->create($request);

        self::assertSame(AbsenceRequest::STATUS_APPROVED, $request->getStatus());
    }

    public function testInactiveTypeIsRejected(): void
    {
        $violation = $this->catchViolation(fn() => $this->service->create(
            $this->newRequest($this->factory->absenceType(active: false), '2030-03-04'),
        ));

        self::assertSame('type_inactive', $violation->rule);
        self::assertSame('typeId', $violation->propertyPath);
    }

    public function testHalfDayRequiresEnabledFeature(): void
    {
        $this->factory->settings(['halfDaysEnabled' => false]);

        $violation = $this->catchViolation(fn() => $this->service->create(
            $this->newRequest($this->factory->absenceType(), '2030-03-04', halfDay: true),
        ));

        self::assertSame('half_days_disabled', $violation->rule);
        self::assertSame('halfDay', $violation->propertyPath);
    }

    public function testDateRulesBecomeViolations(): void
    {
        $type = $this->factory->absenceType();

        $endBeforeStart = $this->catchViolation(fn() => $this->service->create($this->newRequest($type, '2030-03-08', '2030-03-04')));
        self::assertSame('end_before_start', $endBeforeStart->rule);
        self::assertSame('endDate', $endBeforeStart->propertyPath);

        $halfDayRange = $this->catchViolation(fn() => $this->service->create($this->newRequest($type, '2030-03-04', '2030-03-05', halfDay: true)));
        self::assertSame('half_day_single_day', $halfDayRange->rule);
        self::assertSame('halfDay', $halfDayRange->propertyPath);
    }

    public function testTwoHalfDaysShareADayButNotThree(): void
    {
        $type = $this->factory->absenceType();

        $this->service->create($this->newRequest($type, '2030-03-04', halfDay: true));
        $this->service->create($this->newRequest($type, '2030-03-04', halfDay: true));
        $violation = $this->catchViolation(fn() => $this->service->create($this->newRequest($type, '2030-03-04', halfDay: true)));

        self::assertSame('overlaps_existing', $violation->rule);
    }

    public function testHalfDayHolidayLeavesRoomForOneHalfDayOnly(): void
    {
        $type = $this->factory->absenceType();
        $this->factory->holiday('2030-12-24', [$this->user], halfDay: true);

        $this->service->create($this->newRequest($type, '2030-12-24', halfDay: true));
        $violation = $this->catchViolation(fn() => $this->service->create($this->newRequest($type, '2030-12-24', halfDay: true)));

        self::assertSame('overlaps_existing', $violation->rule);
    }

    public function testQuotaShortfallCarriesDayCounts(): void
    {
        $type = $this->factory->absenceType(requiresQuota: true, defaultYearlyQuotaDays: 2.5);
        $this->factory->absenceRequest($this->user, $type, '2030-03-04', '2030-03-05', AbsenceRequest::STATUS_APPROVED);

        // 0.5 days left: a half day fits, a full day does not
        $this->service->create($this->newRequest($type, '2030-03-06', halfDay: true));
        $violation = $this->catchViolation(fn() => $this->service->create($this->newRequest($type, '2030-03-07')));

        self::assertSame('quota_exceeded', $violation->rule);
        self::assertSame(['already' => 2.5, 'quota' => 2.5, 'requested' => 1.0], $violation->parameters);
    }

    public function testCancelPendingAndApprovedButNotRejected(): void
    {
        $type     = $this->factory->absenceType();
        $pending  = $this->factory->absenceRequest($this->user, $type, '2030-03-04');
        $approved = $this->factory->absenceRequest($this->user, $type, '2030-03-05', status: AbsenceRequest::STATUS_APPROVED);
        $rejected = $this->factory->absenceRequest($this->user, $type, '2030-03-06', status: AbsenceRequest::STATUS_REJECTED);

        $this->service->cancel($pending);
        $this->service->cancel($approved);
        $violation = $this->catchViolation(fn() => $this->service->cancel($rejected));

        self::assertSame(AbsenceRequest::STATUS_CANCELLED, $pending->getStatus());
        self::assertSame(AbsenceRequest::STATUS_CANCELLED, $approved->getStatus());
        self::assertSame('cannot_cancel', $violation->rule);
        self::assertSame(409, $violation->status);
    }

    public function testApproveAndRejectSendMails(): void
    {
        $type = $this->factory->absenceType();
        $a    = $this->factory->absenceRequest($this->user, $type, '2030-03-04');
        $b    = $this->factory->absenceRequest($this->user, $type, '2030-03-05');

        self::assertTrue($this->service->approve($a, $this->admin));
        self::assertTrue($this->service->reject($b, $this->admin, '  Busy  '));

        self::assertSame(AbsenceRequest::STATUS_APPROVED, $a->getStatus());
        self::assertSame(AbsenceRequest::STATUS_REJECTED, $b->getStatus());
        self::assertSame('Busy', $b->getRejectReason());
        self::assertCount(2, $this->queuedMails());
    }

    public function testDecisionRules(): void
    {
        $type = $this->factory->absenceType();
        $own  = $this->factory->absenceRequest($this->admin, $type, '2030-03-04');
        $done = $this->factory->absenceRequest($this->user, $type, '2030-03-05', status: AbsenceRequest::STATUS_APPROVED);
        $open = $this->factory->absenceRequest($this->user, $type, '2030-03-06');

        $ownViolation = $this->catchViolation(fn() => $this->service->approve($own, $this->admin));
        self::assertSame('own_request', $ownViolation->rule);
        self::assertSame(403, $ownViolation->status);

        $doneViolation = $this->catchViolation(fn() => $this->service->reject($done, $this->admin, 'x'));
        self::assertSame('not_pending', $doneViolation->rule);
        self::assertSame(409, $doneViolation->status);

        $reasonViolation = $this->catchViolation(fn() => $this->service->reject($open, $this->admin, '   '));
        self::assertSame('reason_required', $reasonViolation->rule);
        self::assertSame(AbsenceRequest::STATUS_PENDING, $open->getStatus());
    }

    private function newRequest(AbsenceType $type, string $start, ?string $end = null, bool $halfDay = false): AbsenceRequest
    {
        $request = new AbsenceRequest();
        $request->setRequestedBy($this->user);
        $request->setType($type);
        $request->setStartDate(new DateTimeImmutable($start));
        $request->setEndDate(new DateTimeImmutable($end ?? $start));
        $request->setHalfDay($halfDay);

        return $request;
    }

    private function queuedMails(): array
    {
        /** @var MessageLoggerListener $logger */
        $logger = static::getContainer()->get('mailer.message_logger_listener');

        return array_filter($logger->getEvents()->getEvents(), fn($event) => $event->isQueued());
    }

    private function catchViolation(callable $fn): DomainRuleViolation
    {
        try {
            $fn();
        } catch (DomainRuleViolation $violation) {
            return $violation;
        }

        self::fail('Expected a DomainRuleViolation');
    }
}
