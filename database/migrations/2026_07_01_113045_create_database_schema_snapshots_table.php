<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_schema_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('database_connection_id')->nullable()->index();
            $table->string('connection_key')->index();
            $table->string('status')->default('pending');
            $table->timestampTz('captured_at')->nullable();
            $table->unsignedInteger('schema_count')->default(0);
            $table->unsignedInteger('table_count')->default(0);
            $table->unsignedInteger('column_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestampsTz();

            $table->index(['connection_key', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_schema_snapshots');
    }
};
