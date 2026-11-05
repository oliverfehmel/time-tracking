<?php

namespace App\Tests\Support;

use App\Entity\AbsenceQuota;
use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\Holiday;
use App\Entity\Settings;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Entity\WorkLocation;
use App\Entity\WorkLocationType;
use App\Repository\SettingsRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates and persists entities for functional tests. Every test runs in a transaction that is rolled back
 * (dama/doctrine-test-bundle), so nothing created here survives the test.
 */
final class TestDataFactory
{
    private static int $sequence = 0;

    public function __construct(private readonly EntityManagerInterface $em) {}

    public function user(?string $email = null, bool $admin = false, int $dailyWorkMinutes = 480, ?string $name = null): User
    {
        $n    = ++self::$sequence;
        $user = new User();
        $user->setEmail($email ?? sprintf('user%d@example.test', $n));
        $user->setName($name ?? sprintf('User %d', $n));
        $user->setRoles($admin ? ['ROLE_ADMIN'] : []);
        $user->setDailyWorkMinutes($dailyWorkMinutes);
        // Not a valid hash: these users only log in via loginUser() or API tokens.
        $user->setPassword('!');

        return $this->save($user);
    }

    public function admin(?string $email = null): User
    {
        return $this->user($email, admin: true);
    }

    /**
     * @param array{halfDaysEnabled?: bool, usersAreAllowedToChangeTimeEntries?: bool, autoPauseAfterSixHours?: int, autoPauseAfterNineHours?: int} $values
     */
    public function settings(array $values = []): Settings
    {
        /** @var SettingsRepository $repo */
        $repo     = $this->em->getRepository(Settings::class);
        $settings = $repo->getOrCreate();

        if (array_key_exists('halfDaysEnabled', $values)) {
            $settings->setHalfDaysEnabled($values['halfDaysEnabled']);
        }
        if (array_key_exists('usersAreAllowedToChangeTimeEntries', $values)) {
            $settings->setUsersAreAllowedToChangeTimeEntries($values['usersAreAllowedToChangeTimeEntries']);
        }
        if (array_key_exists('autoPauseAfterSixHours', $values)) {
            $settings->setAutoPauseAfterSixHours($values['autoPauseAfterSixHours']);
        }
        if (array_key_exists('autoPauseAfterNineHours', $values)) {
            $settings->setAutoPauseAfterNineHours($values['autoPauseAfterNineHours']);
        }

        return $this->save($settings);
    }

    public function absenceType(
        ?string $key = null,
        bool $requiresApproval = true,
        bool $requiresQuota = false,
        ?float $defaultYearlyQuotaDays = null,
        bool $allowOverLimit = false,
        bool $active = true,
        ?string $name = null,
    ): AbsenceType {
        $n    = ++self::$sequence;
        $type = new AbsenceType();
        $type->setKeyName($key ?? sprintf('type%d', $n));
        $type->setName($name ?? sprintf('Type %d', $n));
        $type->setRequiresApproval($requiresApproval);
        $type->setRequiresQuota($requiresQuota);
        $type->setDefaultYearlyQuotaDays($defaultYearlyQuotaDays);
        $type->setAllowOverLimit($allowOverLimit);
        $type->setIsActive($active);

        return $this->save($type);
    }

    public function absenceRequest(
        User $user,
        AbsenceType $type,
        string $start,
        ?string $end = null,
        string $status = AbsenceRequest::STATUS_PENDING,
        bool $halfDay = false,
        ?string $comment = null,
    ): AbsenceRequest {
        $request = new AbsenceRequest();
        $request->setRequestedBy($user);
        $request->setType($type);
        $request->setStartDate(new DateTimeImmutable($start));
        $request->setEndDate(new DateTimeImmutable($end ?? $start));
        $request->setStatus($status);
        $request->setHalfDay($halfDay);
        $request->setComment($comment);

        return $this->save($request);
    }

    public function quota(User $user, AbsenceType $type, int $year, ?float $days, ?bool $allowOverLimit = null): AbsenceQuota
    {
        $quota = new AbsenceQuota();
        $quota->setUser($user);
        $quota->setType($type);
        $quota->setYear($year);
        $quota->setQuotaDays($days);
        $quota->setAllowOverLimit($allowOverLimit);

        return $this->save($quota);
    }

    /**
     * @param User[] $users
     */
    public function holiday(string $date, array $users = [], bool $halfDay = false, ?string $name = null): Holiday
    {
        $holiday = new Holiday();
        $holiday->setName($name ?? sprintf('Holiday %d', ++self::$sequence));
        $holiday->setDate(new DateTimeImmutable($date));
        $holiday->setHalfDay($halfDay);
        foreach ($users as $user) {
            $holiday->addUser($user);
        }

        return $this->save($holiday);
    }

    public function timeEntry(User $user, string $start, ?string $stop): TimeEntry
    {
        $entry = new TimeEntry();
        $entry->setUser($user);
        $entry->setStartedAt(new DateTimeImmutable($start));
        if ($stop !== null) {
            $entry->setStoppedAt(new DateTimeImmutable($stop));
        }

        return $this->save($entry);
    }

    public function workLocationType(?string $key = null, bool $default = false, bool $active = true, ?string $icon = null): WorkLocationType
    {
        $n    = ++self::$sequence;
        $type = new WorkLocationType();
        $type->setKeyName($key ?? sprintf('location%d', $n));
        $type->setName(sprintf('Location %d', $n));
        $type->setIsDefault($default);
        $type->setIsActive($active);
        $type->setIcon($icon);

        return $this->save($type);
    }

    public function workLocation(User $user, string $date, WorkLocationType $type): WorkLocation
    {
        $location = new WorkLocation();
        $location->setUser($user);
        $location->setDate(new DateTimeImmutable($date));
        $location->setLocationType($type);

        return $this->save($location);
    }

    /**
     * @template T of object
     * @param T $entity
     * @return T
     */
    public function save(object $entity): object
    {
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }
}
