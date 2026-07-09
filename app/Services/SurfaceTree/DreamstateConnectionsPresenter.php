<?php

namespace App\Services\SurfaceTree;

use App\Models\Sidecar\Email;
use App\Models\Subconscious\DreamstateCandidate;
use App\Models\Subconscious\DreamstateSensemakerRequest;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Answers "is this impression linked to others?" in plain language. Each
 * dreamed impression gets grouped connection summaries — a human label and a
 * count per relationship kind — instead of raw id lists:
 *
 *   same sidecar thread   → Part of the same conversation
 *   same sidecar sender   → More from this sender
 *   same source path      → From the same source
 *   same Dreamstate run   → Dreamed together
 *   same returned run     → Contributed to the same output
 *
 * Counts come from batched grouped model queries (named columns plus a
 * count aggregate) per listing, never per card. Sources that are missing or
 * unreadable simply contribute no groups — the listing itself never breaks.
 */
class DreamstateConnectionsPresenter
{
    private const LABELS = [
        'same_conversation' => 'Part of the same conversation',
        'same_sender' => 'More from this sender',
        'same_source' => 'From the same source',
        'dreamed_together' => 'Dreamed together',
        'same_output' => 'Contributed to the same output',
    ];

    /**
     * @param  array<string, Model>  $rowsById feed rows keyed by impression id
     * @param  array<string, array<string, mixed>>  $evolutionById evolution meta keyed by impression id
     * @param  array<string, Email|null>  $emailsById sidecar email rows keyed by impression id
     * @return array<string, array<string, mixed>> connections meta keyed by impression id
     */
    public function resolveMany(array $rowsById, array $evolutionById, array $emailsById): array
    {
        if ($rowsById === []) {
            return [];
        }

        $sourceCounts = $this->sourcePathCounts($rowsById);
        $senderCounts = $this->sidecarCounts($this->emailValues($emailsById, 'sender'), 'sender');
        $threadCounts = $this->sidecarCounts($this->emailValues($emailsById, 'thread_id'), 'thread_id');

        $runIds = [];

        foreach ($evolutionById as $evolution) {
            $runId = $this->text($evolution['run_id'] ?? null);

            if ($runId !== null) {
                $runIds[$runId] = true;
            }
        }

        $requestRunCounts = $this->groupedCounts(new DreamstateSensemakerRequest, 'run_id', array_map(strval(...), array_keys($runIds)));
        $candidateRunCounts = $this->groupedCounts(new DreamstateCandidate, 'run_id', array_map(strval(...), array_keys($runIds)));

        $connections = [];

        foreach ($rowsById as $impressionId => $row) {
            $evolution = $evolutionById[$impressionId] ?? [];
            $email = $emailsById[$impressionId] ?? null;
            $runId = $this->text($evolution['run_id'] ?? null);

            $groups = $this->groupList([
                'same_conversation' => $this->othersCount($threadCounts, $this->text($email?->thread_id)),
                'same_sender' => $this->othersCount($senderCounts, $this->text($email?->sender)),
                'same_source' => $this->othersCount($sourceCounts, $this->text($row->getAttribute('source_path'))),
                'dreamed_together' => $this->othersCount($requestRunCounts, $runId),
                'same_output' => $this->text($evolution['packet_id'] ?? null) !== null
                    ? $this->othersCount($candidateRunCounts, $runId)
                    : 0,
            ]);

            $connections[$impressionId] = [
                'connections_available' => true,
                'connection_count' => array_sum(array_column($groups, 'count')),
                'connections' => $groups === [] ? null : $groups,
            ];
        }

        return $connections;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{kind: string, label: string, count: int}>
     */
    private function groupList(array $counts): array
    {
        $groups = [];

        foreach ($counts as $kind => $count) {
            if ($count > 0) {
                $groups[] = [
                    'kind' => $kind,
                    'label' => self::LABELS[$kind],
                    'count' => $count,
                ];
            }
        }

        return $groups;
    }

    /**
     * The impression itself is always part of its own group, so "linked to
     * others" is the group size minus one.
     *
     * @param  array<string, int>  $counts
     */
    private function othersCount(array $counts, ?string $key): int
    {
        if ($key === null || ! isset($counts[$key])) {
            return 0;
        }

        return max(0, $counts[$key] - 1);
    }

    /**
     * @param  array<string, Model>  $rowsById
     * @return array<string, int> listing rows per source_path
     */
    private function sourcePathCounts(array $rowsById): array
    {
        $counts = [];

        foreach ($rowsById as $row) {
            $sourcePath = $this->text($row->getAttribute('source_path'));

            if ($sourcePath !== null) {
                $counts[$sourcePath] = ($counts[$sourcePath] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Grouped sidecar email counts for the listing's senders or threads,
     * over the known emails columns.
     *
     * @param  list<string>  $values
     * @return array<string, int>
     */
    private function sidecarCounts(array $values, string $column): array
    {
        if ($values === []) {
            return [];
        }

        try {
            return $this->countRows(Email::query()
                ->toBase()
                ->select($column)
                ->selectRaw('count(*) as connection_count')
                ->whereIn($column, $values)
                ->groupBy($column)
                ->get()
                ->all(), $column);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private function groupedCounts(Model $model, string $column, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        try {
            return $this->countRows($model->newQuery()
                ->toBase()
                ->select($column)
                ->selectRaw('count(*) as connection_count')
                ->whereIn($column, $keys)
                ->groupBy($column)
                ->get()
                ->all(), $column);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<object>  $rows
     * @return array<string, int>
     */
    private function countRows(array $rows, string $column): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $key = $this->text($row->{$column} ?? null);

            if ($key !== null) {
                $counts[$key] = (int) ($row->connection_count ?? 0);
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, Email|null>  $emailsById
     * @return list<string>
     */
    private function emailValues(array $emailsById, string $column): array
    {
        $values = [];

        foreach ($emailsById as $email) {
            $value = $this->text($email?->getAttribute($column));

            if ($value !== null) {
                $values[$value] = true;
            }
        }

        return array_map(strval(...), array_keys($values));
    }

    private function text(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_resource($value)) {
            return null;
        }

        return (string) $value;
    }
}
