<?php

namespace App\Models\Impressions;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only legacy impressions source. The table is not guaranteed to exist
 * on every organism; when present it carries a string impression_id business
 * key, so that is the safest primary key for read-only lookups.
 */
class SensemadeImpression extends Model
{
    use ReadOnlyModel;

    protected $connection = 'impressions';

    protected $table = 'sensemade_impressions';

    protected $primaryKey = 'impression_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
