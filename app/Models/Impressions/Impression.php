<?php

namespace App\Models\Impressions;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only base impressions table. Its id column is a uuid, so the key is
 * declared as a non-incrementing string; the table has no Laravel timestamp
 * columns.
 */
class Impression extends Model
{
    use ReadOnlyModel;

    protected $connection = 'impressions';

    protected $table = 'impressions';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
