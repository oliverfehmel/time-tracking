<?php

namespace App\Api\View;

use App\Api\ApiDates;
use App\Entity\ApiToken;
use App\Entity\Settings;
use App\Entity\User;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'The authenticated user, the token in use and the feature flags that affect the API')]
final readonly class MeView
{
    public function __construct(
        public UserView $user,
        public TokenInfoView $token,
        public FeaturesView $features,
    ) {}

    public static function from(User $user, ApiToken $token, Settings $settings): self
    {
        return new self(
            user: UserView::from($user),
            token: new TokenInfoView(
                name: $token->getName(),
                scope: $token->getScope(),
                expiresAt: ApiDates::dateTime($token->getExpiresAt()),
            ),
            features: new FeaturesView(
                halfDaysEnabled: $settings->isHalfDaysEnabled(),
                timeEntryEditingAllowed: $settings->isUsersAreAllowedToChangeTimeEntries(),
            ),
        );
    }
}
