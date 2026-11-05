<?php

namespace App\Security\Voter;

use App\Entity\AbsenceRequest;
use App\Entity\ApiToken;
use App\Entity\Holiday;
use App\Entity\TimeEntry;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may see (and, within the domain rules, change) a piece of data: admins everything, users their own data.
 * Holidays belong to the users they are assigned to.
 *
 * @extends Voter<string, User|TimeEntry|AbsenceRequest|Holiday|ApiToken>
 */
final class DataAccessVoter extends Voter
{
    public const string ACCESS = 'DATA_ACCESS';

    public function __construct(private readonly AccessDecisionManagerInterface $decisionManager) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ACCESS
            && ($subject instanceof User
                || $subject instanceof TimeEntry
                || $subject instanceof AbsenceRequest
                || $subject instanceof Holiday
                || $subject instanceof ApiToken);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->decisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return match (true) {
            $subject instanceof User           => $subject->getId() === $user->getId(),
            $subject instanceof TimeEntry      => $subject->getUser()?->getId() === $user->getId(),
            $subject instanceof AbsenceRequest => $subject->getRequestedBy()->getId() === $user->getId(),
            $subject instanceof ApiToken       => $subject->getUser()->getId() === $user->getId(),
            $subject instanceof Holiday        => $subject->getUsers()->exists(static fn($k, User $u) => $u->getId() === $user->getId()),
        };
    }
}
