<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DatabaseConnection extends Model
{
    protected $fillable = [
        'name',
        'connection_key',
        'host',
        'port',
        'database',
        'username',
        'description',
        'is_enabled',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function schemaSnapshots(): HasMany
    {
        return $this->hasMany(DatabaseSchemaSnapshot::class);
    }

    public function latestSchemaSnapshot(): HasOne
    {
        return $this->hasOne(DatabaseSchemaSnapshot::class)
            ->latestOfMany('captured_at');
    }
}
