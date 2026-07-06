<?php

namespace App\Services\OrganState\ActivitySources;

use App\Services\OrganState\OrganActivitySnapshot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * Base for organ activity providers: each concrete source declares an
 * explicit list of read-only Eloquent models and timestamp columns to probe.
 * No schema crawling — a candidate whose table or column is absent on this
 * organism is simply skipped.
 */
abstract class ModelActivitySource implements OrganActivitySource
{
    /**
     * Explicit (model class, timestamp column) pairs to probe, in priority
     * order for tie-breaking.
     *
     * @return list<array{class-string<\Illuminate\Database\Eloquent\Model>, string}>
     */
    abstract protected function candidates(): array;

    protected function emptySource(): string
    {
        return $this->connectionKey();
    }

    public function latestActivity(): OrganActivitySnapshot
    {
        $candidates = $this->candidates();

        if ($candidates !== []) {
            // Connection-level failures must surface as error summaries, so
            // connect once up front; missing tables/columns are skipped below.
            (new $candidates[0][0])->getConnection()->getPdo();
        }

        $latest = null;
        $source = $this->emptySource();
        $columnsByModel = [];

        foreach ($candidates as [$modelClass, $column]) {
            $columnsByModel[$modelClass] ??= $this->columns($modelClass);

            if (! in_array($column, $columnsByModel[$modelClass], true)) {
                continue;
            }

            $timestamp = $this->timestamp($modelClass::query()->max($column));

            if ($timestamp === null || ($latest !== null && $timestamp->lessThanOrEqualTo($latest))) {
                continue;
            }

            $latest = $timestamp;
            $source = sprintf('%s:%s.%s', $this->connectionKey(), $this->unqualifiedTable($modelClass), $column);
        }

        return new OrganActivitySnapshot($this->connectionKey(), $latest, $source);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @return list<string>
     */
    private function columns(string $modelClass): array
    {
        $model = new $modelClass;

        try {
            return $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function unqualifiedTable(string $modelClass): string
    {
        return Str::afterLast((new $modelClass)->getTable(), '.');
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value->toDateTimeImmutable());
        }

        if (! filled($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
