<?php

/** @noinspection PhpClassCanBeReadonlyInspection */

namespace App\Events\Bloodstream;

use Carbon\CarbonImmutable;

class BloodstreamObserverChanged
{
    public function __construct(
        public readonly string $subject,
        public readonly CarbonImmutable $receivedAt,
        public readonly ?string $owner = null,
        public readonly ?string $event = null,
        public readonly ?string $publisher = null,
        public readonly ?CarbonImmutable $publishedAt = null,
        public readonly ?CarbonImmutable $emittedAt = null,
    ) {}
}
