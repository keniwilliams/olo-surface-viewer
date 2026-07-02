<?php

namespace App\Models\Bloodstream;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class ReadOnlyBloodstreamBuilder extends Builder
{
    public function firstOrCreate(array $attributes = [], Closure|array $values = [])
    {
        $this->rejectWrite();
    }

    public function createOrFirst(array $attributes = [], Closure|array $values = [])
    {
        $this->rejectWrite();
    }

    public function updateOrCreate(array $attributes, Closure|array $values = [])
    {
        $this->rejectWrite();
    }

    public function incrementOrCreate(array $attributes, string $column = 'count', $default = 1, $step = 1, array $extra = [])
    {
        $this->rejectWrite();
    }

    public function create(array $attributes = [])
    {
        $this->rejectWrite();
    }

    public function createQuietly(array $attributes = [])
    {
        $this->rejectWrite();
    }

    public function forceCreate(array $attributes)
    {
        $this->rejectWrite();
    }

    public function forceCreateQuietly(array $attributes = [])
    {
        $this->rejectWrite();
    }

    public function update(array $values)
    {
        $this->rejectWrite();
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->rejectWrite();
    }

    public function touch($column = null)
    {
        $this->rejectWrite();
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->rejectWrite();
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->rejectWrite();
    }

    public function delete()
    {
        $this->rejectWrite();
    }

    public function forceDelete()
    {
        $this->rejectWrite();
    }

    public function __call($method, $parameters)
    {
        if (in_array($method, [
            'insert',
            'insertGetId',
            'insertOrIgnore',
            'insertOrIgnoreReturning',
            'insertOrIgnoreUsing',
            'insertUsing',
            'truncate',
            'updateOrInsert',
        ], true)) {
            $this->rejectWrite();
        }

        return parent::__call($method, $parameters);
    }

    private function rejectWrite(): never
    {
        throw new LogicException('Bloodstream models are read-only for Surface Viewer.');
    }
}
