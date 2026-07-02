<?php

namespace App\Events\Bloodstream;

use Carbon\CarbonImmutable;

class BloodstreamObserverChanged
{
    public function __construct(
        public readonly string $subject,
        public readonly CarbonImmutable $receivedAt,
    ) {}
}
