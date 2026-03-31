<?php

namespace App\Tests\Service;

use App\Entity\AbsenceQuota;
use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\User;
use App\Repository\AbsenceQuotaRepository;
use App\Repository\AbsenceRequestRepository;
use App\Repository\AbsenceTypeRepository;
use App\Repository\HolidayRepository;
use App\Service\AbsenceDayCalculator;
use App\Service\AbsenceQuotaService;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AbsenceQuotaServiceTest extends TestCase
{
    private AbsenceTypeRepository&Stub $typeRepo;
    private AbsenceQuotaRepository&Stub $quotaRepo;
    private AbsenceRequestRepository&Stub $requestRepo;
    private AbsenceQuotaService $service;

    protected function setUp(): void
    {
        $this->typeRepo    = $this->createStub(AbsenceTypeRepository::class);
        $this->quotaRepo   = $this->createStub(AbsenceQuotaRepository::class);
        $this->requestRepo = $this->createStub(AbsenceRequestRepository::class);

        $holidayRepo = $this->createStub(HolidayRepository::class);
        $holidayRepo->method('findForUserBetween')->willReturn([]);

        $this->service = new AbsenceQuotaService(
            $this->typeRepo,
            $this->quotaRepo,
            $this->requestRepo,
            new AbsenceDayCalculator($holidayRepo),
        );
    }

    public function testGetQuotaOverview_noRequests_allZero(): void
    {
        $user = new User();
        $type = $this->makeType(1, 'Urlaub', 'vacation', quota: true, defaultDays: 30);

        $this->typeRepo->method('findActive')->willReturn([$type]);
        $this->quotaRepo->method('findOneFor')->willReturn(null);
        $this->requestRepo->method('findForUserInYear')->willReturn([]);

        $result = $this->service->getQuotaOverview($user, 2024);

        $this->assertCount(1, $result);
        $row = $result[0];

        $this->assertSame($type, $row['type']);
        $this->assertSame(30, $row['quotaDays']);
        $this->assertSame(0, $row['usedApprovedDays']);
        $this->assertSame(0, $row['usedPendingDays']);
        $this->assertSame(30, $row['remainingDays']);
    }

    public function testGetQuotaOverview_approvedDaysReduceRemaining(): void
    {
        $user    = new User();
        $type    = $this->makeType(1, 'Urlaub', 'vacation', quota: true, defaultDays: 30);
        $request = $this->makeRequest($type, '2024-07-01', '2024-07-05', AbsenceRequest::STATUS_APPROVED);

        $this->typeRepo->method('findActive')->willReturn([$type]);
        $this->quotaRepo->method('findOneFor')->willReturn(null);
        $this->requestRepo->method('findForUserInYear')->willReturn([$request]);

        $result = $this->service->getQuotaOverview($user, 2024);

        $row = $result[0];
        // Mon 01.07 – Fri 05.07 = 5 workdays
        $this->assertSame(5, $row['usedApprovedDays']);
        $this->assertSame(0, $row['usedPendingDays']);
        $this->assertSame(25, $row['remainingDays']); // 30 - 5
    }

    public function testGetQuotaOverview_pendingDaysTrackedSeparately(): void
    {
        $user    = new User();
        $type    = $this->makeType(1, 'Urlaub', 'vacation', quota: true, defaultDays: 30);
        $request = $this->makeRequest($type, '2024-07-01', '2024-07-05', AbsenceRequest::STATUS_PENDING);

        $this->typeRepo->method('findActive')->willReturn([$type]);
        $this->quotaRepo->method('findOneFor')->willReturn(null);
        $this->requestRepo->method('findForUserInYear')->willReturn([$request]);

        $result = $this->service->getQuotaOverview($user, 2024);

        $row = $result[0];
        $this->assertSame(0, $row['usedApprovedDays']);
        $this->assertSame(5, $row['usedPendingDays']);
        $this->assertSame(30, $row['remainingDays']); // pending doesn't reduce remaining
    }

    public function testGetQuotaOverview_customQuotaOverridesDefault(): void
    {
        $user  = new User();
        $type  = $this->makeType(1, 'Urlaub', 'vacation', quota: true, defaultDays: 30);
        $quota = $this->makeQuota($type, 20); // custom: only 20 days

        $this->typeRepo->method('findActive')->willReturn([$type]);
        $this->quotaRepo->method('findOneFor')->willReturn($quota);
        $this->requestRepo->method('findForUserInYear')->willReturn([]);

        $result = $this->service->getQuotaOverview($user, 2024);

        $this->assertSame(20, $result[0]['quotaDays']);
        $this->assertSame(20, $result[0]['remainingDays']);
    }

    public function testGetQuotaOverview_typeWithoutQuota_remainingIsNull(): void
    {
        $user = new User();
        $type = $this->makeType(1, 'Krankheit', 'sick', quota: false, defaultDays: null);

        $this->typeRepo->method('findActive')->willReturn([$type]);
        $this->quotaRepo->method('findOneFor')->willReturn(null);
        $this->requestRepo->method('findForUserInYear')->willReturn([]);

        $result = $this->service->getQuotaOverview($user, 2024);

        $row = $result[0];
        $this->assertNull($row['quotaDays']);
        $this->assertNull($row['remainingDays']);
    }

    public function testGetQuotaOverview_remainingNeverBelowZero(): void
    {
        $user     = new User();
        $type     = $this->makeType(1, 'Urlaub', 'vacation', quota: true, defaultDays: 3);
        $request1 = $this->makeRequest($type, '2024-07-01', '2024-07-03', AbsenceRequest::STATUS_APPROVED); // 3 days
        $request2 = $this->makeRequest($type, '2024-07-08', '2024-07-09', AbsenceRequest::STATUS_APPROVED); // 2 days

        $this->typeRepo->method('findActive')->willReturn([$type]);
        $this->quotaRepo->method('findOneFor')->willReturn(null);
        $this->requestRepo->method('findForUserInYear')->willReturn([$request1, $request2]);

        $result = $this->service->getQuotaOverview($user, 2024);

        $this->assertSame(0, $result[0]['remainingDays']); // clamped, not negative
    }

    public function testGetQuotaOverview_multipleTypes(): void
    {
        $user     = new User();
        $typeVac  = $this->makeType(1, 'Urlaub', 'vacation', quota: true, defaultDays: 30);
        $typeSick = $this->makeType(2, 'Krankheit', 'sick', quota: false, defaultDays: null);

        $this->typeRepo->method('findActive')->willReturn([$typeVac, $typeSick]);
        $this->quotaRepo->method('findOneFor')->willReturn(null);
        $this->requestRepo->method('findForUserInYear')->willReturn([]);

        $result = $this->service->getQuotaOverview($user, 2024);

        $this->assertCount(2, $result);
        $this->assertSame(30, $result[0]['quotaDays']);
        $this->assertNull($result[1]['quotaDays']);
    }

    // --- Helpers ---

    private function makeType(int $id, string $name, string $key, bool $quota, ?int $defaultDays): AbsenceType
    {
        $type = new AbsenceType();
        $type->setName($name);
        $type->setKeyName($key);
        $type->setRequiresQuota($quota);
        $type->setDefaultYearlyQuotaDays($defaultDays);

        // Set private $id via reflection so the service can use it as a map key
        (new ReflectionClass($type))->getProperty('id')->setValue($type, $id);

        return $type;
    }

    private function makeRequest(AbsenceType $type, string $start, string $end, string $status): AbsenceRequest
    {
        $request = new AbsenceRequest();
        $request->setRequestedBy(new User());
        $request->setType($type);
        $request->setStartDate(new DateTimeImmutable($start));
        $request->setEndDate(new DateTimeImmutable($end));
        $request->setStatus($status);

        return $request;
    }

    private function makeQuota(AbsenceType $type, int $days): AbsenceQuota
    {
        $quota = new AbsenceQuota();
        $quota->setUser(new User());
        $quota->setType($type);
        $quota->setYear(2024);
        $quota->setQuotaDays($days);

        return $quota;
    }
}
