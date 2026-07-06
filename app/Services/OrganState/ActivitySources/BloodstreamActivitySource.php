<?php

namespace App\Services\OrganState\ActivitySources;

use App\Models\Bloodstream\ContractMemory;
use App\Models\Bloodstream\SubjectMemory;

class BloodstreamActivitySource extends ModelActivitySource
{
    public function connectionKey(): string
    {
        return 'bloodstream';
    }

    protected function emptySource(): string
    {
        return 'bloodstream:observer_memory';
    }

    protected function candidates(): array
    {
        return [
            [SubjectMemory::class, 'last_seen_at'],
            [SubjectMemory::class, 'updated_at'],
            [ContractMemory::class, 'updated_at'],
        ];
    }
}
