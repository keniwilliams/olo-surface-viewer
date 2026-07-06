<?php

namespace App\Services\OrganState;

use Carbon\CarbonImmutable;

class OrganActivitySnapshot
{
    public function __construct(
        public readonly string $connectionKey,
        public readonly ?CarbonImmutable $observedAt,
        public readonly string $source,
    ) {}
}
