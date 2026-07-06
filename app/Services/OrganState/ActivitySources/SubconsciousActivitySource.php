<?php

namespace App\Services\OrganState\ActivitySources;

use App\Models\Subconscious\DreamstateReturnPacket;
use App\Models\Subconscious\DreamstateRun;
use App\Models\Subconscious\DreamstateSensemakerRequest;

class SubconsciousActivitySource extends ModelActivitySource
{
    public function connectionKey(): string
    {
        return 'subconscious';
    }

    protected function candidates(): array
    {
        return [
            [DreamstateRun::class, 'completed_at'],
            [DreamstateRun::class, 'started_at'],
            [DreamstateSensemakerRequest::class, 'completed_at'],
            [DreamstateSensemakerRequest::class, 'requested_at'],
            [DreamstateReturnPacket::class, 'created_at'],
        ];
    }
}
