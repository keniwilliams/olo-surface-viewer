<?php

namespace App\Models\Subconscious;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only dreamstate run record; lives in the dreamstate_schema schema
 * with a uuid run_id key.
 */
class DreamstateRun extends Model
{
    use ReadOnlyModel;

    protected $connection = 'subconscious';

    protected $table = 'dreamstate_schema.dreamstate_run';

    protected $primaryKey = 'run_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
