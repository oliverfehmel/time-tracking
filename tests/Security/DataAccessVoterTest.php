<?php

namespace App\Tests\Security;

use App\Entity\AbsenceRequest;
use App\Entity\ApiToken;
use App\Entity\Holiday;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Security\Voter\DataAccessVoter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class DataAccessVoterTest extends TestCase
{
    private User $me;
    private User $other;

    protected function setUp(): void
    {
        $this->me    = $this->user(1);
        $this->other = $this->user(2);
    }

    public function testUserAccessesOwnDataOnly(): void
    {
        $voter = $this->voter(admin: false);

        foreach ($this->subjectsOwnedBy($this->me) as $subject) {
            self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, $subject), $subject::class);
        }
        foreach ($this->subjectsOwnedBy($this->other) as $subject) {
            self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, $subject), $subject::class);
        }
    }

    public function testAdminAccessesEverything(): void
    {
        $voter = $this->voter(admin: true);

        foreach ($this->subjectsOwnedBy($this->other) as $subject) {
            self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, $subject), $subject::class);
        }
    }

    public function testUnsupportedSubjectsAndAttributesAbstain(): void
    {
        $voter = $this->voter(admin: true);
        $token = new UsernamePasswordToken($this->me, 'api', ['ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new \stdClass(), [DataAccessVoter::ACCESS]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, $this->me, ['EDIT']));
    }

    /**
     * @return list<object>
     */
    private function subjectsOwnedBy(User $owner): array
    {
        $entry = new TimeEntry();
        $entry->setUser($owner);

        $request = new AbsenceRequest();
        $request->setRequestedBy($owner);

        $holiday = new Holiday();
        $holiday->addUser($owner);

        $apiToken = new ApiToken($owner, 'x', str_repeat('a', 64), 'tt_x', ApiToken::SCOPE_READ, new DateTimeImmutable());

        return [$owner, $entry, $request, $holiday, $apiToken];
    }

    private function vote(DataAccessVoter $voter, object $subject): int
    {
        return $voter->vote(new UsernamePasswordToken($this->me, 'api', $this->me->getRoles()), $subject, [DataAccessVoter::ACCESS]);
    }

    private function voter(bool $admin): DataAccessVoter
    {
        $decisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $decisionManager->method('decide')->willReturn($admin);

        return new DataAccessVoter($decisionManager);
    }

    private function user(int $id): User
    {
        $user = new User();
        $user->setEmail("user$id@example.test");
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
