<?php

namespace App\Models\Subconscious;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only dreamstate return packet record in the dreamstate_schema schema.
 */
class DreamstateReturnPacket extends Model
{
    use ReadOnlyModel;

    protected $connection = 'subconscious';

    protected $table = 'dreamstate_schema.dreamstate_return_packet';

    public $timestamps = false;

    protected $guarded = [];
}
