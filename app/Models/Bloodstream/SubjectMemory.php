<?php

namespace App\Models\Bloodstream;

class SubjectMemory extends ReadOnlyBloodstreamModel
{
    protected $table = 'subject_memory';

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'seen_count' => 'integer',
        'metadata_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
