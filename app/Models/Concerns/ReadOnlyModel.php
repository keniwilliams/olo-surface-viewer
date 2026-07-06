<?php

namespace App\Models\Concerns;

use RuntimeException;

/**
 * Blocks every Eloquent write path, both on model instances (save, delete,
 * push, increment, ...) and on the query builder (Model::query()->update(...)
 * and friends) via ReadOnlyEloquentBuilder.
 */
trait ReadOnlyModel
{
    public function newEloquentBuilder($query): ReadOnlyEloquentBuilder
    {
        return new ReadOnlyEloquentBuilder($query);
    }

    public function save(array $options = [])
    {
        $this->throwReadOnlyModel(__FUNCTION__);
    }

    public function update(array $attributes = [], array $options = [])
    {
        $this->throwReadOnlyModel(__FUNCTION__);
    }

    public function push()
    {
        $this->throwReadOnlyModel(__FUNCTION__);
    }

    public function delete()
    {
        $this->throwReadOnlyModel(__FUNCTION__);
    }

    public function forceDelete()
    {
        $this->throwReadOnlyModel(__FUNCTION__);
    }

    public static function destroy($ids)
    {
        throw new RuntimeException(static::class.' is read-only; destroy() is not allowed.');
    }

    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        $this->throwReadOnlyModel($method);
    }

    private function throwReadOnlyModel(string $method): never
    {
        throw new RuntimeException(static::class." is read-only; {$method}() is not allowed.");
    }
}
