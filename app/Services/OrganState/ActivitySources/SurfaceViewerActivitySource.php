<?php

namespace App\Services\OrganState\ActivitySources;

use App\Models\SurfaceViewer\SchemaSnapshotRecord;

class SurfaceViewerActivitySource extends ModelActivitySource
{
    public function connectionKey(): string
    {
        return 'surface_viewer';
    }

    protected function candidates(): array
    {
        return [
            [SchemaSnapshotRecord::class, 'captured_at'],
            [SchemaSnapshotRecord::class, 'updated_at'],
        ];
    }
}
