<?php

namespace App\Models\Bloodstream;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use LogicException;

abstract class ReadOnlyBloodstreamModel extends Model
{
    use SoftDeletes;

    protected $connection = 'bloodstream';

    protected $guarded = ['*'];

    /**
     * @return ReadOnlyBloodstreamBuilder<*>
     */
    public function newEloquentBuilder($query): ReadOnlyBloodstreamBuilder
    {
        /** @var QueryBuilder $query */
        return new ReadOnlyBloodstreamBuilder($query);
    }

    public function save(array $options = [])
    {
        $this->rejectWrite();
    }

    public function update(array $attributes = [], array $options = [])
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

    public function restore()
    {
        $this->rejectWrite();
    }

    public function restoreQuietly()
    {
        $this->rejectWrite();
    }

    public function touch($attribute = null)
    {
        $this->rejectWrite();
    }

    private function rejectWrite(): never
    {
        throw new LogicException('Bloodstream models are read-only for Surface Viewer.');
    }
}
