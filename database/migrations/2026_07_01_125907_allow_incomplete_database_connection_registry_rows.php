<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table database_connections alter column host drop not null');
        DB::statement('alter table database_connections alter column port drop not null');
        DB::statement('alter table database_connections alter column database drop not null');
        DB::statement('alter table database_connections alter column username drop not null');
    }

    public function down(): void
    {
        DB::statement('alter table database_connections alter column host set not null');
        DB::statement('alter table database_connections alter column port set not null');
        DB::statement('alter table database_connections alter column database set not null');
        DB::statement('alter table database_connections alter column username set not null');
    }
};
