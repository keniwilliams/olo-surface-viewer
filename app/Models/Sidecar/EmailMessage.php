<?php

namespace App\Models\Sidecar;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only fallback sidecar source for organisms that expose email data as
 * email_messages instead of emails.
 */
class EmailMessage extends Model
{
    use ReadOnlyModel;

    protected $connection = 'sidecar';

    protected $table = 'email_messages';

    public $timestamps = false;

    protected $guarded = [];
}
