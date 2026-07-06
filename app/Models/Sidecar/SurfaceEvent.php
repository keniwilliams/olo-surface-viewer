<?php

namespace App\Models\Sidecar;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only sidecar surface event record (created_at).
 */
class SurfaceEvent extends Model
{
    use ReadOnlyModel;

    protected $connection = 'sidecar';

    protected $table = 'surface_events';

    public $timestamps = false;

    protected $guarded = [];
}
