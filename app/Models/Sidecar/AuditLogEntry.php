<?php

namespace App\Models\Sidecar;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only sidecar audit log record (occurred_at / created_at).
 */
class AuditLogEntry extends Model
{
    use ReadOnlyModel;

    protected $connection = 'sidecar';

    protected $table = 'audit_log';

    public $timestamps = false;

    protected $guarded = [];
}
