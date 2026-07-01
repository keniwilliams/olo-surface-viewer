<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseTableSchema extends Model
{
    protected $fillable = [
        'database_schema_snapshot_id',
        'schema_name',
        'table_name',
        'table_type',
        'row_count',
        'columns',
        'primary_keys',
        'foreign_keys',
        'indexes',
    ];

    protected $casts = [
        'row_count' => 'integer',
        'columns' => 'array',
        'primary_keys' => 'array',
        'foreign_keys' => 'array',
        'indexes' => 'array',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DatabaseSchemaSnapshot::class, 'database_schema_snapshot_id');
    }
}
