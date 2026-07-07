<?php

namespace App\Models\Impressions;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only email sensemaking projection from the impressions organism.
 */
class EmailImpression extends Model
{
    use ReadOnlyModel;

    protected $connection = 'impressions';

    protected $table = 'email_impressions';

    protected $primaryKey = 'impression_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'email' => 'array',
        'state' => 'array',
    ];
}
