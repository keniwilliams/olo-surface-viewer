<?php

namespace App\Services\OrganState\ActivitySources;

use App\Services\OrganState\OrganActivitySnapshot;

interface OrganActivitySource
{
    public function connectionKey(): string;

    public function latestActivity(): OrganActivitySnapshot;
}
