<?php

namespace App\Service;

use App\Entity\AbsenceRequest;
use App\Entity\User;
use App\Repository\AbsenceRequestRepository;
use App\Repository\AbsenceTypeRepository;
use App\Repository\HolidayRepository;
use App\Repository\UserRepository;
use DateInterval;
use DateTimeImmutable;

final class AbsenceOverviewBuilder
{
    // Reihenfolge bestimmt Farb-Zuweisung
    private const array TYPE_PALETTE = [
        'bg-success', 'bg-danger', 'bg-info',
        'bg-warning', 'bg-primary', 'bg-secondary', 'bg-dark',
    ];

    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly AbsenceRequestRepository $absenceRepo,
        private readonly HolidayRepository $holidayRepo,
        private readonly AbsenceTypeRepository $typeRepo,
    ) {}

    public function build(DateTimeImmutable $monthStart): array
    {
        $monthEndExclusive = $monthStart->modify('first day of next month');
        $monthEndInclusive = $monthEndExclusive->sub(new DateInterval('P1D'));
        $daysInMonth       = (int) $monthStart->format('t');

        $users   = $this->userRepo->findAllOrderedByName();
        $userIds = $this->extractUserIds($users);

        $absences = $this->absenceRepo->findOverlappingForUsersAndMonth(
            $userIds,
            $monthStart,
            $monthEndInclusive,
            [AbsenceRequest::STATUS_APPROVED, AbsenceRequest::STATUS_PENDING],
        );

        $holidays = $this->holidayRepo->findForUsersInMonth($userIds, $monthStart, $monthEndExclusive);

        return [
            'monthStart'        => $monthStart,
            'daysInMonth'       => $daysInMonth,
            'days'              => $this->buildDayHeaders($monthStart, $daysInMonth),
            'users'             => $users,
            'barsByUser'        => $this->buildBarsByUser($users, $absences, $monthStart, $monthEndInclusive),
            'holidayDaysByUser' => $this->buildHolidayDaysByUser($users, $holidays),
            'prevMonth'         => $monthStart->sub(new DateInterval('P1M')),
            'nextMonth'         => $monthStart->add(new DateInterval('P1M')),
            'statusPending'     => AbsenceRequest::STATUS_PENDING,
            'typeClassMap'      => $this->buildTypeClassMap(),
            'typeLabelMap'      => $this->buildTypeLabelMap(),
        ];
    }

    /** @param User[] $users */
    private function extractUserIds(array $users): array
    {
        return array_values(
            array_filter(
                array_map(static fn(User $u) => $u->getId(), $users),
                static fn($id) => $id !== null,
            )
        );
    }

    private function buildDayHeaders(DateTimeImmutable $monthStart, int $daysInMonth): array
    {
        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date   = $monthStart->setDate(
                (int) $monthStart->format('Y'),
                (int) $monthStart->format('m'),
                $d,
            );
            $days[] = [
                'day' => $d,
                'iso' => $date->format('Y-m-d'),
                'dow' => (int) $date->format('N'), // 1=Mon..7=Sun
            ];
        }
        return $days;
    }

    /** @param User[] $users */
    private function buildBarsByUser(
        array $users,
        array $absences,
        DateTimeImmutable $monthStart,
        DateTimeImmutable $monthEndInclusive,
    ): array {
        $bars = array_fill_keys(
            array_filter(array_map(fn(User $u) => $u->getId(), $users)),
            [],
        );

        foreach ($absences as $a) {
            $uid = $a->getRequestedBy()->getId();
            if ($uid === null || !array_key_exists($uid, $bars)) {
                continue;
            }

            $start = max($a->getStartDate(), $monthStart);
            $end   = min($a->getEndDate(), $monthEndInclusive);

            $startDay = (int) $start->format('j');
            $endDay   = (int) $end->format('j');

            if ($endDay < $startDay) {
                continue;
            }

            $bars[$uid][] = [
                'id'       => $a->getId(),
                'startDay' => $startDay,
                'endDay'   => $endDay,
                'days'     => $endDay - $startDay + 1,
                'typeName' => $a->getType()->getName(),
                'typeKey'  => $a->getType()->getKeyName(),
                'status'   => $a->getStatus(),
                'comment'  => $a->getComment(),
            ];
        }

        return $bars;
    }

    /** @param User[] $users */
    private function buildHolidayDaysByUser(array $users, array $holidays): array
    {
        $map = array_fill_keys(
            array_filter(array_map(fn(User $u) => $u->getId(), $users)),
            [],
        );

        foreach ($holidays as $h) {
            $day = (int) $h->getDate()->format('j');
            foreach ($h->getUsers() as $u) {
                $uid = $u->getId();
                if ($uid === null || !array_key_exists($uid, $map)) {
                    continue;
                }
                $map[$uid][$day][] = $h->getName();
            }
        }

        return $map;
    }

    private function buildTypeClassMap(): array
    {
        $map = [];
        foreach ($this->typeRepo->findActiveOrdered() as $i => $t) {
            $map[$t->getKeyName()] = self::TYPE_PALETTE[$i % count(self::TYPE_PALETTE)];
        }
        return $map;
    }

    private function buildTypeLabelMap(): array
    {
        $map = [];
        foreach ($this->typeRepo->findActiveOrdered() as $t) {
            $map[$t->getKeyName()] = $t->getName();
        }
        return $map;
    }
}
