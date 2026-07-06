<?php

namespace App\Services\SurfaceTree;

use App\Models\Impressions\Impression;
use App\Models\Impressions\ImpressionDreamstateFeed;
use App\Models\Impressions\SensemadeImpression;
use App\Services\SurfaceTree\Concerns\ReadsEloquentSources;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Read path for filesystem-projected impressions. All database access goes
 * through read-only Eloquent models; the first available source wins.
 */
class ImpressionsFilesystemFeed
{
    use ReadsEloquentSources;

    private const SOURCES = [
        ImpressionDreamstateFeed::class,
        SensemadeImpression::class,
        Impression::class,
    ];

    private const ORDER_COLUMNS = ['observed_at', 'sensemade_at', 'created_at'];

    private const ID_COLUMNS = ['impression_id', 'uuid', 'id'];

    /**
     * Latest path-bearing impressions, filtered before the row limit so old
     * path-bearing rows are not crowded out by newer pathless ones.
     *
     * @return list<Model>
     */
    public function latestPathBearingRows(): array
    {
        $modelClass = $this->firstAvailableSource(self::SOURCES);

        if ($modelClass === null) {
            return [];
        }

        try {
            $columns = $this->columns($modelClass);
            $query = $modelClass::query();

            if (in_array('source_path', $columns, true)) {
                $query
                    ->whereNotNull('source_path')
                    ->where('source_path', '<>', '');
            }

            foreach (self::ORDER_COLUMNS as $orderColumn) {
                if (in_array($orderColumn, $columns, true)) {
                    $query->orderByDesc($orderColumn);
                    break;
                }
            }

            return $query->limit(500)->get()->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function rawCorpus(string $impressionId): ?string
    {
        $modelClass = $this->firstAvailableSource(self::SOURCES);

        if ($modelClass === null) {
            return null;
        }

        try {
            $columns = $this->columns($modelClass);

            if (! in_array('raw_corpus', $columns, true)) {
                return null;
            }

            $idColumn = null;

            foreach (self::ID_COLUMNS as $candidate) {
                if (in_array($candidate, $columns, true)) {
                    $idColumn = $candidate;
                    break;
                }
            }

            if ($idColumn === null) {
                return null;
            }

            $row = $modelClass::query()->where($idColumn, $impressionId)->first();

            return $row === null ? null : $this->rawCorpusText($row);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The impressions table stores raw_corpus as bytea; PDO surfaces it as a
     * stream resource. Decode it the same way the dreamstate feed view does:
     * utf8 corpora become text, anything else is base64-encoded.
     */
    private function rawCorpusText(Model $row): ?string
    {
        $raw = $row->getAttribute('raw_corpus');

        if (is_resource($raw)) {
            $raw = stream_get_contents($raw);
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $encoding = $row->getAttribute('raw_corpus_encoding');

        if (($encoding === null || $encoding === 'utf8') && mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        return base64_encode($raw);
    }

}
