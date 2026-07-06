<?php

namespace App\Services\OrganState\ActivitySources;

use App\Models\Sidecar\AuditLogEntry;
use App\Models\Sidecar\Email;
use App\Models\Sidecar\ScheduledRunnerRun;
use App\Models\Sidecar\SurfaceEvent;

class SidecarActivitySource extends ModelActivitySource
{
    public function connectionKey(): string
    {
        return 'sidecar';
    }

    protected function candidates(): array
    {
        return [
            [Email::class, 'received_at'],
            [Email::class, 'updated_at'],
            [ScheduledRunnerRun::class, 'finished_at'],
            [ScheduledRunnerRun::class, 'started_at'],
            [AuditLogEntry::class, 'occurred_at'],
            [SurfaceEvent::class, 'created_at'],
        ];
    }
}
