<?php

namespace App\Services\SurfaceTree;

use App\Models\Impressions\CameraLensScenePayload;
use App\Models\Impressions\Impression;
use App\Models\Impressions\ImpressionDreamstateFeed;
use App\Models\Impressions\SensemadeImpression;
use App\Services\SurfaceTree\Concerns\ReadsEloquentSources;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Read path for the dreamstate and camera_lens domain roots. Each domain has
 * its own dedicated source table/view rather than a shared table filtered by
 * a domain column: dreamstate reads the whole impressions_dreamstate_feed
 * (the general feed Dreamstate consumes), camera_lens reads the separate
 * camera_lens_scene_payloads table the Camera Lens project publishes into.
 * All database access goes through read-only Eloquent models; the first
 * available source per domain wins.
 */
class DomainImpressionsFeed
{
    use ReadsEloquentSources;

    private const DREAMSTATE_SOURCES = [
        ImpressionDreamstateFeed::class,
        SensemadeImpression::class,
        Impression::class,
    ];

    private const CAMERA_LENS_SOURCES = [
        CameraLensScenePayload::class,
    ];

    private const ORDER_COLUMNS = ['observed_at', 'sensemade_at', 'created_at'];

    /**
     * @return list<Model>
     */
    public function latestRowsForDomain(string $domain): array
    {
        $modelClass = $this->firstAvailableSource($this->sourcesForDomain($domain));

        if ($modelClass === null) {
            return [];
        }

        try {
            $columns = $this->columns($modelClass);
            $query = $modelClass::query();

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

    /**
     * The connection:table/view the domain listing currently reads from —
     * the "source database/view" receipt for the technical drawer.
     */
    public function sourceViewForDomain(string $domain): ?string
    {
        $modelClass = $this->firstAvailableSource($this->sourcesForDomain($domain));

        if ($modelClass === null) {
            return null;
        }

        $model = new $modelClass;

        return $model->getConnectionName().':'.$model->getTable();
    }

    /**
     * @return list<class-string<Model>>
     */
    private function sourcesForDomain(string $domain): array
    {
        return match ($domain) {
            'dreamstate' => self::DREAMSTATE_SOURCES,
            'camera_lens' => self::CAMERA_LENS_SOURCES,
            default => [],
        };
    }
}
