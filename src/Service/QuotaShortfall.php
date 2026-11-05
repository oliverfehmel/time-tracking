<?php

namespace App\Service;

/**
 * Why a new absence request does not fit into the quota (all values in days, half days as 0.5).
 */
final readonly class QuotaShortfall
{
    public function __construct(
        public float $alreadyRequestedDays,
        public float $quotaDays,
        public float $requestedDays,
    ) {}
}
