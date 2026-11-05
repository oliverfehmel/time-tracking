<?php

namespace App\Tests\Service;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\Holiday;
use App\Entity\User;
use App\Repository\AbsenceRequestRepository;
use App\Repository\AbsenceTypeRepository;
use App\Repository\HolidayRepository;
use App\Repository\UserRepository;
use App\Service\AbsenceOverviewBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AbsenceOverviewBuilderHalfDayTest extends TestCase
{
    private const MONTH = '2024-12-01';

    public function testBars_halfDayFlagAndSlots(): void
    {
        $user = $this->makeUser(7);
        $type = $this->makeType();

        $full  = $this->makeRequest($user, $type, '2024-12-02', '2024-12-04');
        $half1 = $this->makeRequest($user, $type, '2024-12-10', '2024-12-10', halfDay: true);
        $half2 = $this->makeRequest($user, $type, '2024-12-10', '2024-12-10', halfDay: true);

        $bars = $this->makeBuilder([$user], [$full, $half1, $half2], [])->build(new DateTimeImmutable(self::MONTH))['barsByUser'][7];

        $this->assertFalse($bars[0]['halfDay']);
        $this->assertNull($bars[0]['halfSlot']);
        $this->assertSame(3, $bars[0]['days']);

        $this->assertTrue($bars[1]['halfDay']);
        $this->assertSame(0, $bars[1]['halfSlot']);
        $this->assertTrue($bars[2]['halfDay']);
        $this->assertSame(1, $bars[2]['halfSlot']);
    }

    public function testHalfHolidayDays_onlyDaysWithoutFullHoliday(): void
    {
        $user = $this->makeUser(7);

        $christmasEve = (new Holiday())->setName('Heiligabend')->setDate(new DateTimeImmutable('2024-12-24'))->setHalfDay(true)->addUser($user);
        $christmas    = (new Holiday())->setName('1. Weihnachtstag')->setDate(new DateTimeImmutable('2024-12-25'))->addUser($user);
        $nye          = (new Holiday())->setName('Silvester')->setDate(new DateTimeImmutable('2024-12-31'))->setHalfDay(true)->addUser($user);
        $nyeFull      = (new Holiday())->setName('Firmenfeiertag')->setDate(new DateTimeImmutable('2024-12-31'))->addUser($user);

        $result = $this->makeBuilder([$user], [], [$christmasEve, $christmas, $nye, $nyeFull])->build(new DateTimeImmutable(self::MONTH));

        $this->assertSame([24 => true], $result['halfHolidayDaysByUser'][7]);
        $this->assertSame(['Silvester', 'Firmenfeiertag'], $result['holidayDaysByUser'][7][31]);
    }

    // --- Helpers ---

    private function makeUser(int $id): User
    {
        $user = new User();
        (new ReflectionClass($user))->getProperty('id')->setValue($user, $id);
        return $user;
    }

    private function makeType(): AbsenceType
    {
        $type = new AbsenceType();
        $type->setName('Urlaub');
        $type->setKeyName('vacation');
        return $type;
    }

    private function makeRequest(User $user, AbsenceType $type, string $start, string $end, bool $halfDay = false): AbsenceRequest
    {
        return (new AbsenceRequest())
            ->setRequestedBy($user)
            ->setType($type)
            ->setStartDate(new DateTimeImmutable($start))
            ->setEndDate(new DateTimeImmutable($end))
            ->setStatus(AbsenceRequest::STATUS_APPROVED)
            ->setHalfDay($halfDay);
    }

    private function makeBuilder(array $users, array $absences, array $holidays): AbsenceOverviewBuilder
    {
        $userRepo    = $this->createStub(UserRepository::class);
        $absenceRepo = $this->createStub(AbsenceRequestRepository::class);
        $holidayRepo = $this->createStub(HolidayRepository::class);
        $typeRepo    = $this->createStub(AbsenceTypeRepository::class);

        $userRepo->method('findAllOrderedByName')->willReturn($users);
        $absenceRepo->method('findOverlappingForUsersAndMonth')->willReturn($absences);
        $holidayRepo->method('findForUsersInMonth')->willReturn($holidays);
        $typeRepo->method('findActiveOrdered')->willReturn([]);

        return new AbsenceOverviewBuilder($userRepo, $absenceRepo, $holidayRepo, $typeRepo);
    }
}
