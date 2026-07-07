<?php

namespace App\Models\Sidecar;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only sidecar emails source. The sidecar schema is owned by another
 * organism, so the default id key is a documented fallback for read-only
 * lookups; rows are matched by message/source references, not by key.
 */
class Email extends Model
{
    use ReadOnlyModel;

    protected $connection = 'sidecar';

    protected $table = 'emails';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
