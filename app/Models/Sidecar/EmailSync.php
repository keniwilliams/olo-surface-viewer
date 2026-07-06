<?php

namespace App\Models\Sidecar;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only fallback sidecar source for organisms that expose email data as
 * email_syncs instead of emails.
 */
class EmailSync extends Model
{
    use ReadOnlyModel;

    protected $connection = 'sidecar';

    protected $table = 'email_syncs';

    public $timestamps = false;

    protected $guarded = [];
}
