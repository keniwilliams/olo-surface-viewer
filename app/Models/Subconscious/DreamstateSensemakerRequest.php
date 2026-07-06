<?php

namespace App\Models\Subconscious;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only dreamstate sensemaker request record in the dreamstate_schema
 * schema.
 */
class DreamstateSensemakerRequest extends Model
{
    use ReadOnlyModel;

    protected $connection = 'subconscious';

    protected $table = 'dreamstate_schema.dreamstate_sensemaker_request';

    public $timestamps = false;

    protected $guarded = [];
}
