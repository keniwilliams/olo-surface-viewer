<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DatabaseSchemaInspector
{
    public function inspect(string $connectionName): array
    {
        $tables = $this->tables($connectionName);

        return [
            'connection' => $connectionName,
            'schemas' => collect($tables)->pluck('schema_name')->unique()->values()->all(),
            'tables' => array_map(
                fn (array $table): array => [
                    ...$table,
                    'row_count' => null,
                    'columns' => $this->columns($connectionName, $table['schema_name'], $table['table_name']),
                    'primary_keys' => $this->primaryKeys($connectionName, $table['schema_name'], $table['table_name']),
                    'foreign_keys' => $this->foreignKeys($connectionName, $table['schema_name'], $table['table_name']),
                    'indexes' => $this->indexes($connectionName, $table['schema_name'], $table['table_name']),
                ],
                $tables
            ),
        ];
    }

    private function tables(string $connectionName): array
    {
        $rows = DB::connection($connectionName)->select(
            <<<'SQL'
            select
                table_schema as schema_name,
                table_name,
                table_type
            from information_schema.tables
            where table_schema not in ('pg_catalog', 'information_schema')
            order by table_schema, table_name
            SQL
        );

        return $this->rowsToArrays($rows);
    }

    private function columns(string $connectionName, string $schemaName, string $tableName): array
    {
        $rows = DB::connection($connectionName)->select(
            <<<'SQL'
            select
                column_name,
                ordinal_position,
                data_type,
                is_nullable,
                column_default,
                character_maximum_length,
                numeric_precision,
                numeric_scale
            from information_schema.columns
            where table_schema = ?
              and table_name = ?
            order by ordinal_position
            SQL,
            [$schemaName, $tableName]
        );

        return $this->rowsToArrays($rows);
    }

    private function primaryKeys(string $connectionName, string $schemaName, string $tableName): array
    {
        $rows = DB::connection($connectionName)->select(
            <<<'SQL'
            select
                kcu.column_name,
                kcu.ordinal_position,
                tc.constraint_name
            from information_schema.table_constraints tc
            join information_schema.key_column_usage kcu
              on kcu.constraint_schema = tc.constraint_schema
             and kcu.constraint_name = tc.constraint_name
             and kcu.table_schema = tc.table_schema
             and kcu.table_name = tc.table_name
            where tc.constraint_type = 'PRIMARY KEY'
              and tc.table_schema = ?
              and tc.table_name = ?
            order by kcu.ordinal_position
            SQL,
            [$schemaName, $tableName]
        );

        return $this->rowsToArrays($rows);
    }

    private function foreignKeys(string $connectionName, string $schemaName, string $tableName): array
    {
        $rows = DB::connection($connectionName)->select(
            <<<'SQL'
            select
                tc.constraint_name,
                kcu.column_name,
                ccu.table_schema as referenced_schema,
                ccu.table_name as referenced_table,
                ccu.column_name as referenced_column
            from information_schema.table_constraints tc
            join information_schema.key_column_usage kcu
              on kcu.constraint_schema = tc.constraint_schema
             and kcu.constraint_name = tc.constraint_name
             and kcu.table_schema = tc.table_schema
             and kcu.table_name = tc.table_name
            join information_schema.constraint_column_usage ccu
              on ccu.constraint_schema = tc.constraint_schema
             and ccu.constraint_name = tc.constraint_name
            where tc.constraint_type = 'FOREIGN KEY'
              and tc.table_schema = ?
              and tc.table_name = ?
            order by tc.constraint_name, kcu.ordinal_position
            SQL,
            [$schemaName, $tableName]
        );

        return $this->rowsToArrays($rows);
    }

    private function indexes(string $connectionName, string $schemaName, string $tableName): array
    {
        $rows = DB::connection($connectionName)->select(
            <<<'SQL'
            select
                schemaname as schema_name,
                tablename as table_name,
                indexname,
                indexdef
            from pg_indexes
            where schemaname = ?
              and tablename = ?
            order by indexname
            SQL,
            [$schemaName, $tableName]
        );

        return $this->rowsToArrays($rows);
    }

    private function rowsToArrays(array $rows): array
    {
        return array_map(
            fn (object $row): array => (array) $row,
            $rows
        );
    }
}
