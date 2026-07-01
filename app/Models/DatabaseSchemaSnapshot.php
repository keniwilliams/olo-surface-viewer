<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatabaseSchemaSnapshot extends Model
{
    protected $fillable = [
        'database_connection_id',
        'connection_key',
        'status',
        'captured_at',
        'schema_count',
        'table_count',
        'column_count',
        'error_message',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'schema_count' => 'integer',
        'table_count' => 'integer',
        'column_count' => 'integer',
    ];

    public function databaseConnection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(DatabaseTableSchema::class);
    }
}
