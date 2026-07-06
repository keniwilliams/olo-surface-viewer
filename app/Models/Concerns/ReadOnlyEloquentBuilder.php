<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class ReadOnlyEloquentBuilder extends Builder
{
    public function update(array $values)
    {
        $this->throwReadOnly(__FUNCTION__);
    }

    public function delete()
    {
        $this->throwReadOnly(__FUNCTION__);
    }

    public function forceDelete()
    {
        $this->throwReadOnly(__FUNCTION__);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->throwReadOnly(__FUNCTION__);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->throwReadOnly(__FUNCTION__);
    }

    public function insert(array $values)
    {
        $this->throwReadOnly(__FUNCTION__);
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $this->throwReadOnly(__FUNCTION__);
    }

    public function insertOrIgnore(array $values)
    {
        $this->throwReadOnly(__FUNCTION__);
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->throwReadOnly(__FUNCTION__);
    }

    public function updateOrInsert(array $attributes, $values = [])
    {
        $this->throwReadOnly(__FUNCTION__);
    }

    private function throwReadOnly(string $method): never
    {
        throw new RuntimeException(sprintf('%s is read-only; %s() is not allowed.', $this->getModel()::class, $method));
    }
}
