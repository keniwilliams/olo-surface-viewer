<?php

namespace App\Services\SurfaceTree;

use App\Models\Impressions\ImpressionDreamstateFeed;
use App\Services\SurfaceTree\Concerns\ReadsEloquentSources;
use Throwable;

/**
 * Resolves the type/provenance of dreamed impressions. Dreamstate remembers
 * what it dreamed about, Impressions remembers what the thing was: detailed
 * type identity is thinned out after Acquire, so each dreamstate
 * impression_id is joined back to the canonical
 * olo_impressions.impressions_dreamstate_feed view and its memory_kind
 * becomes the display kind. Anything the feed cannot vouch for is reported
 * unresolved rather than guessed at.
 */
class DreamstateProvenanceResolver
{
    use ReadsEloquentSources;

    public const CONTRACT_VERSION = 'impressions_dreamstate_feed_v1';

    private const FEED_COLUMNS = ['impression_id', 'memory_kind', 'source_ref', 'contract_version'];

    /**
     * @param  list<string>  $impressionIds
     * @return array<string, array<string, mixed>> provenance meta keyed by impression id
     */
    public function resolveMany(array $impressionIds): array
    {
        if ($impressionIds === []) {
            return [];
        }

        try {
            if (! $this->sourceExists(new ImpressionDreamstateFeed)) {
                return $this->allUnresolved($impressionIds, 'impressions_dreamstate_feed is not available');
            }

            $missingColumns = array_values(array_diff(self::FEED_COLUMNS, $this->columns(ImpressionDreamstateFeed::class)));

            if ($missingColumns !== []) {
                return $this->allUnresolved($impressionIds, 'feed is missing columns: '.implode(', ', $missingColumns));
            }

            $rows = ImpressionDreamstateFeed::query()
                ->select(self::FEED_COLUMNS)
                ->whereIn('impression_id', $impressionIds)
                ->get()
                ->keyBy('impression_id');
        } catch (Throwable) {
            return $this->allUnresolved($impressionIds, 'feed could not be queried');
        }

        $provenance = [];

        foreach ($impressionIds as $impressionId) {
            $row = $rows->get($impressionId);

            if ($row === null) {
                $provenance[$impressionId] = $this->unresolved('no feed row for impression');

                continue;
            }

            $contractVersion = $row->getAttribute('contract_version');

            if ($contractVersion !== self::CONTRACT_VERSION) {
                $provenance[$impressionId] = $this->unresolved(
                    'unexpected contract_version: '.($contractVersion === null ? 'null' : (string) $contractVersion),
                );

                continue;
            }

            $provenance[$impressionId] = [
                'memory_kind' => $row->getAttribute('memory_kind'),
                'memory_source_ref' => $row->getAttribute('source_ref'),
                'contract_version' => $contractVersion,
                'provenance_resolved' => true,
            ];
        }

        return $provenance;
    }

    /**
     * @param  list<string>  $impressionIds
     * @return array<string, array<string, mixed>>
     */
    private function allUnresolved(array $impressionIds, string $error): array
    {
        return array_fill_keys($impressionIds, $this->unresolved($error));
    }

    /**
     * @return array<string, mixed>
     */
    private function unresolved(string $error): array
    {
        return [
            'provenance_resolved' => false,
            'provenance_resolution_error' => $error,
        ];
    }
}
