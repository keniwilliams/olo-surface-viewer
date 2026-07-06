<?php

namespace App\Services\SurfaceTree;

use App\Models\Impressions\Impression;
use App\Models\Impressions\SensemadeImpression;
use App\Models\Sidecar\Email;
use App\Models\Sidecar\EmailMessage;
use App\Models\Sidecar\EmailSync;
use App\Services\SurfaceTree\Concerns\ReadsEloquentSources;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Read path for email-projected impressions and their sidecar enrichment.
 * All database access goes through read-only Eloquent models.
 */
class EmailImpressionsFeed
{
    use ReadsEloquentSources;

    private const IMPRESSION_SOURCES = [
        SensemadeImpression::class,
        Impression::class,
    ];

    private const SIDECAR_SOURCES = [
        Email::class,
        EmailMessage::class,
        EmailSync::class,
    ];

    private const ORDER_COLUMNS = ['observed_at', 'sensemade_at', 'created_at'];

    /**
     * @return list<Model>
     */
    public function latestEmailRows(): array
    {
        $modelClass = $this->firstAvailableSource(self::IMPRESSION_SOURCES);

        if ($modelClass === null) {
            return [];
        }

        try {
            $columns = $this->columns($modelClass);
            $query = $modelClass::query()->limit(250);

            if (in_array('domain', $columns, true)) {
                $query->where('domain', 'email');
            }

            foreach (self::ORDER_COLUMNS as $orderColumn) {
                if (in_array($orderColumn, $columns, true)) {
                    $query->orderByDesc($orderColumn);
                    break;
                }
            }

            return $query->get()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<Model>
     */
    public function sidecarEmailRows(): array
    {
        $modelClass = $this->firstAvailableSource(self::SIDECAR_SOURCES);

        if ($modelClass === null) {
            return [];
        }

        try {
            return $modelClass::query()->limit(250)->get()->all();
        } catch (Throwable) {
            return [];
        }
    }
}
