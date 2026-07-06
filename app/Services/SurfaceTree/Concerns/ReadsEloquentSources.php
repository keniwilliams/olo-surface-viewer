<?php

namespace App\Services\SurfaceTree\Concerns;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Shared source selection for read-only Eloquent feeds: pick the first model
 * whose table or view exists on its own connection.
 */
trait ReadsEloquentSources
{
    /**
     * @param  list<class-string<Model>>  $sources
     * @return class-string<Model>|null
     */
    private function firstAvailableSource(array $sources): ?string
    {
        foreach ($sources as $modelClass) {
            if ($this->sourceExists(new $modelClass)) {
                return $modelClass;
            }
        }

        return null;
    }

    private function sourceExists(Model $model): bool
    {
        try {
            if ($model->getConnection()->getSchemaBuilder()->hasTable($model->getTable())) {
                return true;
            }
        } catch (Throwable) {
            // Fall through to the driver-level probe below.
        }

        // hasTable() can miss Postgres views; to_regclass resolves both.
        try {
            $connection = $model->getConnection();

            if ($connection->getDriverName() !== 'pgsql') {
                return false;
            }

            $result = $connection->selectOne('select to_regclass(?) as relation', ['public.'.$model->getTable()]);

            return ($result->relation ?? null) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return list<string>
     */
    private function columns(string $modelClass): array
    {
        $model = new $modelClass;

        return $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
    }
}
