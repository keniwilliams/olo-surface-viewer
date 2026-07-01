<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
