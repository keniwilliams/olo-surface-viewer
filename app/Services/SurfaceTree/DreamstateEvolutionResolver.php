<?php

namespace App\Services\SurfaceTree;

use App\Models\Subconscious\DreamstateCandidate;
use App\Models\Subconscious\DreamstateReturnPacket;
use App\Models\Subconscious\DreamstateSensemakerRequest;
use Throwable;

/**
 * Resolves how far each dreamed impression moved through Dreamstate:
 *
 *   Observed → Selected for Dreamstate → Became candidate → Matured
 *   → Returned → Settled by Sensemaker
 *
 * Lineage lives in the subconscious dreamstate_schema tables: candidates and
 * sensemaker requests carry the impression_id, return packets hang off the
 * candidate's run. Each impression reports the highest state the lineage can
 * prove, plus the ids behind it for the technical drawer. Missing tables or
 * partial lineage degrade to fewer steps — never to an error.
 */
class DreamstateEvolutionResolver
{
    private const STAGE_LABELS = [
        'observed' => 'Not evolved yet',
        'selected' => 'Selected for Dreamstate',
        'candidate' => 'Became candidate',
        'matured' => 'Matured',
        'returned' => 'Returned from Dreamstate',
        'settled' => 'Settled by Sensemaker',
    ];

    // Candidate statuses that show the candidate moved past initial
    // candidacy. Today's feeds only publish 'pending'; these are the
    // contract's forward states.
    private const MATURED_CANDIDATE_STATUSES = ['matured', 'ready', 'accepted', 'promoted'];

    /**
     * @param  list<string>  $impressionIds
     * @return array<string, array<string, mixed>> evolution meta keyed by impression id
     */
    public function resolveMany(array $impressionIds): array
    {
        if ($impressionIds === []) {
            return [];
        }

        $candidatesById = $this->candidatesByImpression($impressionIds);
        $requestsById = $this->requestsByImpression($impressionIds);

        if ($candidatesById === null && $requestsById === null) {
            // No lineage source is reachable; report nothing rather than a
            // misleading "not evolved yet".
            return [];
        }

        $packetsByRun = $this->packetsByRun(array_values(array_filter(array_map(
            fn (object $candidate) => $this->stringValue($candidate->run_id ?? null),
            $candidatesById ?? [],
        ))));

        $evolution = [];

        foreach ($impressionIds as $impressionId) {
            $evolution[$impressionId] = $this->evolutionMeta(
                ($candidatesById ?? [])[$impressionId] ?? null,
                ($requestsById ?? [])[$impressionId] ?? null,
                $packetsByRun,
            );
        }

        return $evolution;
    }

    /**
     * @param  array<string, object>  $packetsByRun
     * @return array<string, mixed>
     */
    private function evolutionMeta(?object $candidate, ?object $request, array $packetsByRun): array
    {
        $candidateStatus = $this->stringValue($candidate->status ?? null);
        $candidateRunId = $this->stringValue($candidate->run_id ?? null);
        $packet = $candidateRunId !== null ? ($packetsByRun[$candidateRunId] ?? null) : null;

        $requestStatus = $this->stringValue($request->status ?? null);
        $settled = $request !== null
            && ($requestStatus === 'complete' || $this->stringValue($request->completed_at ?? null) !== null);

        $matured = $candidateStatus !== null
            && in_array(strtolower($candidateStatus), self::MATURED_CANDIDATE_STATUSES, true);

        $steps = [];

        if ($candidate !== null || $request !== null) {
            $steps[] = self::STAGE_LABELS['selected'];
        }

        if ($candidate !== null) {
            $steps[] = self::STAGE_LABELS['candidate'];
        }

        if ($matured) {
            $steps[] = self::STAGE_LABELS['matured'];
        }

        if ($packet !== null) {
            $steps[] = self::STAGE_LABELS['returned'];
        }

        if ($settled) {
            $steps[] = self::STAGE_LABELS['settled'];
        }

        $stage = match (true) {
            $settled => 'settled',
            $packet !== null => 'returned',
            $matured => 'matured',
            $candidate !== null => 'candidate',
            $request !== null => 'selected',
            default => 'observed',
        };

        return [
            'evolution_stage' => $stage,
            'evolution_label' => self::STAGE_LABELS[$stage],
            'evolution_steps' => $steps === [] ? null : $steps,
            'run_id' => $candidateRunId ?? $this->stringValue($request->run_id ?? null),
            'candidate_id' => $this->stringValue($candidate->candidate_id ?? null),
            'candidate_status' => $candidateStatus,
            'packet_id' => $packet === null ? null : $this->stringValue($packet->packet_id ?? null),
            'sensemaker_request_id' => $this->stringValue($request->request_id ?? null),
            'sensemaker_status' => $requestStatus,
        ];
    }

    /**
     * @param  list<string>  $impressionIds
     * @return array<string, object>|null null when the source is unreachable
     */
    private function candidatesByImpression(array $impressionIds): ?array
    {
        try {
            $rows = DreamstateCandidate::query()
                ->select(['candidate_id', 'run_id', 'impression_id', 'status'])
                ->whereIn('impression_id', $impressionIds)
                ->get();
        } catch (Throwable) {
            return null;
        }

        $byImpression = [];

        foreach ($rows as $row) {
            $impressionId = $this->stringValue($row->impression_id ?? null);

            if ($impressionId !== null) {
                $byImpression[$impressionId] ??= $row;
            }
        }

        return $byImpression;
    }

    /**
     * @param  list<string>  $impressionIds
     * @return array<string, object>|null null when the source is unreachable
     */
    private function requestsByImpression(array $impressionIds): ?array
    {
        try {
            $rows = DreamstateSensemakerRequest::query()
                ->select(['request_id', 'run_id', 'impression_id', 'status', 'completed_at'])
                ->whereIn('impression_id', $impressionIds)
                ->get();
        } catch (Throwable) {
            return null;
        }

        $byImpression = [];

        foreach ($rows as $row) {
            $impressionId = $this->stringValue($row->impression_id ?? null);

            if ($impressionId === null) {
                continue;
            }

            // A completed request outranks whichever request came first.
            $current = $byImpression[$impressionId] ?? null;

            if ($current === null || ($this->stringValue($current->status ?? null) !== 'complete' && $this->stringValue($row->status ?? null) === 'complete')) {
                $byImpression[$impressionId] = $row;
            }
        }

        return $byImpression;
    }

    /**
     * @param  list<string>  $runIds
     * @return array<string, object>
     */
    private function packetsByRun(array $runIds): array
    {
        if ($runIds === []) {
            return [];
        }

        try {
            $rows = DreamstateReturnPacket::query()
                ->select(['packet_id', 'run_id', 'status'])
                ->whereIn('run_id', array_values(array_unique($runIds)))
                ->get();
        } catch (Throwable) {
            return [];
        }

        $byRun = [];

        foreach ($rows as $row) {
            $runId = $this->stringValue($row->run_id ?? null);

            if ($runId !== null) {
                $byRun[$runId] ??= $row;
            }
        }

        return $byRun;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_resource($value)) {
            return null;
        }

        return (string) $value;
    }
}
