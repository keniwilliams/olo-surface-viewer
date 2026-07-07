<?php

namespace App\Services\SurfaceTree;

use App\Models\Impressions\Impression;
use App\Models\Impressions\ImpressionDreamstateFeed;
use App\Models\Impressions\SensemadeImpression;
use App\Services\SurfaceTree\Concerns\ReadsEloquentSources;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Read path for impressions belonging to a single domain (dreamstate,
 * camera_lens). All database access goes through read-only Eloquent models;
 * the first available source that carries a domain column wins.
 */
class DomainImpressionsFeed
{
    use ReadsEloquentSources;

    private const SOURCES = [
        ImpressionDreamstateFeed::class,
        SensemadeImpression::class,
        Impression::class,
    ];

    private const ORDER_COLUMNS = ['observed_at', 'sensemade_at', 'created_at'];

    /**
     * @return list<Model>
     */
    public function latestRowsForDomain(string $domain): array
    {
        $modelClass = $this->firstAvailableSource(self::SOURCES);

        if ($modelClass === null) {
            return [];
        }

        try {
            $columns = $this->columns($modelClass);

            if (! in_array('domain', $columns, true)) {
                return [];
            }

            $query = $modelClass::query()->where('domain', $domain);

            foreach (self::ORDER_COLUMNS as $orderColumn) {
                if (in_array($orderColumn, $columns, true)) {
                    $query->orderByDesc($orderColumn);
                    break;
                }
            }

            return $query->limit(200)->get()->all();
        } catch (Throwable) {
            return [];
        }
    }
}
