<?php

namespace App\Models\Sidecar;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only sidecar scheduled runner run record (started_at / finished_at).
 */
class ScheduledRunnerRun extends Model
{
    use ReadOnlyModel;

    protected $connection = 'sidecar';

    protected $table = 'scheduled_runner_runs';

    public $timestamps = false;

    protected $guarded = [];
}
