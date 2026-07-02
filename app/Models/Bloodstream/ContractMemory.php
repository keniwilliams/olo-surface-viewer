<?php

namespace App\Models\Bloodstream;

class ContractMemory extends ReadOnlyBloodstreamModel
{
    protected $table = 'contract_memory';

    protected $casts = [
        'schema_json' => 'array',
        'metadata_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
