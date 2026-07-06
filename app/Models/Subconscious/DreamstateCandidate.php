<?php

namespace App\Models\Subconscious;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only dreamstate candidate record in the dreamstate_schema schema.
 */
class DreamstateCandidate extends Model
{
    use ReadOnlyModel;

    protected $connection = 'subconscious';

    protected $table = 'dreamstate_schema.dreamstate_candidates';

    public $timestamps = false;

    protected $guarded = [];
}
