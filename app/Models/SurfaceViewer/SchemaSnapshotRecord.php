<?php

namespace App\Models\SurfaceViewer;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of the local database_schema_snapshots table, pinned to the
 * observed surface_viewer connection. The writable app model is
 * App\Models\DatabaseSchemaSnapshot; this one exists so organ state reads
 * stay read-only and connection-explicit.
 */
class SchemaSnapshotRecord extends Model
{
    use ReadOnlyModel;

    protected $connection = 'surface_viewer';

    protected $table = 'database_schema_snapshots';

    public $timestamps = false;

    protected $guarded = [];
}
