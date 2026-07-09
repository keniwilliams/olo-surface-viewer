<?php

namespace App\Services\SurfaceTree;

use App\Models\Impressions\ImpressionDreamstateFeed;
use Throwable;

/**
 * Resolves the type/provenance of dreamed impressions. Dreamstate remembers
 * what it dreamed about, Impressions remembers what the thing was: each
 * dreamstate impression_id is joined back to the canonical
 * impressions_dreamstate_feed contract and its memory_kind becomes the
 * display kind. Anything the feed cannot vouch for — a missing row, a
 * drifted contract_version, or an unreadable feed — is reported unresolved
 * rather than guessed at.
 */
class DreamstateProvenanceResolver
{
    /**
     * @param  list<int|string>  $impressionIds
     * @return array<string, array<string, mixed>> provenance meta keyed by impression id
     */
    public function resolveMany(array $impressionIds): array
    {
        if ($impressionIds === []) {
            return [];
        }

        try {
            $rows = ImpressionDreamstateFeed::query()
                ->select(['impression_id', 'memory_kind', 'memory_source_ref', 'contract_version'])
                ->whereIn('impression_id', $impressionIds)
                ->get()
                ->keyBy('impression_id');
        } catch (Throwable) {
            return array_fill_keys(
                array_map(strval(...), $impressionIds),
                $this->unresolved('impressions_dreamstate_feed could not be queried'),
            );
        }

        $provenance = [];

        foreach ($impressionIds as $impressionId) {
            $row = $rows->get($impressionId);

            if ($row === null) {
                $provenance[$impressionId] = $this->unresolved('no feed row for impression');

                continue;
            }

            if ($row->contract_version !== ImpressionDreamstateFeed::CONTRACT_VERSION) {
                $provenance[$impressionId] = $this->unresolved(
                    'unexpected contract_version: '.($row->contract_version ?? 'null'),
                );

                continue;
            }

            $provenance[$impressionId] = [
                'memory_kind' => $row->memory_kind,
                'memory_source_ref' => $row->memory_source_ref,
                'contract_version' => $row->contract_version,
                'provenance_resolved' => true,
            ];
        }

        return $provenance;
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
