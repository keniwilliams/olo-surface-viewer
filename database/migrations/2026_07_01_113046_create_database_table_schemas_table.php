<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_table_schemas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('database_schema_snapshot_id')->index();
            $table->string('schema_name');
            $table->string('table_name');
            $table->string('table_type')->nullable();
            $table->unsignedBigInteger('row_count')->nullable();
            $table->jsonb('columns')->default(new Expression("'[]'::jsonb"));
            $table->jsonb('primary_keys')->default(new Expression("'[]'::jsonb"));
            $table->jsonb('foreign_keys')->default(new Expression("'[]'::jsonb"));
            $table->jsonb('indexes')->default(new Expression("'[]'::jsonb"));
            $table->timestampsTz();

            $table->index(['schema_name', 'table_name']);
            $table->unique(
                ['database_schema_snapshot_id', 'schema_name', 'table_name'],
                'database_table_schemas_snapshot_schema_table_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_table_schemas');
    }
};
