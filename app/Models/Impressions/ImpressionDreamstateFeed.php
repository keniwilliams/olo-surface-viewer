<?php

namespace App\Models\Impressions;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only projection over the impressions_dreamstate_feed Postgres view.
 */
class ImpressionDreamstateFeed extends Model
{
    use ReadOnlyModel;

    protected $connection = 'impressions';

    protected $table = 'impressions_dreamstate_feed';

    protected $primaryKey = 'impression_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
