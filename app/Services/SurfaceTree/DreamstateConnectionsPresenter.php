<?php

namespace App\Services\SurfaceTree;

use App\Models\Sidecar\Email;
use App\Models\Subconscious\DreamstateCandidate;
use App\Models\Subconscious\DreamstateSensemakerRequest;
use App\Services\SurfaceTree\Concerns\ReadsEloquentSources;
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
 * Counts come from batched grouped queries (named columns + aggregates) per
 * listing, never per card. Sources that are missing or unreadable simply
 * contribute no groups — the listing itself never breaks.
 */
class DreamstateConnectionsPresenter
{
    use ReadsEloquentSources;

    private const LABELS = [
        'same_conversation' => 'Part of the same conversation',
        'same_sender' => 'More from this sender',
        'same_source' => 'From the same source',
        'dreamed_together' => 'Dreamed together',
        'same_output' => 'Contributed to the same output',
    ];

    /**
     * @param  array<string, mixed>  $rowsById feed rows keyed by impression id
     * @param  array<string, array<string, mixed>>  $evolutionById evolution meta keyed by impression id
     * @param  array<string, object|null>  $emailRowsById sidecar email rows keyed by impression id
     * @return array<string, array<string, mixed>> connections meta keyed by impression id
     */
    public function resolveMany(array $rowsById, array $evolutionById, array $emailRowsById): array
    {
        if ($rowsById === []) {
            return [];
        }

        $sourceCounts = $this->sourcePathCounts($rowsById);
        $senderCounts = $this->sidecarCounts($this->emailValues($emailRowsById, ['sender', 'from_email']), ['sender', 'from_email']);
        $threadCounts = $this->sidecarCounts($this->emailValues($emailRowsById, ['thread_id']), ['thread_id']);

        $runIds = [];

        foreach ($evolutionById as $evolution) {
            $runId = $this->stringValue($evolution['run_id'] ?? null);

            if ($runId !== null) {
                $runIds[$runId] = true;
            }
        }

        $requestRunCounts = $this->groupedCounts(new DreamstateSensemakerRequest, 'run_id', array_keys($runIds));
        $candidateRunCounts = $this->groupedCounts(new DreamstateCandidate, 'run_id', array_keys($runIds));

        $connections = [];

        foreach ($rowsById as $impressionId => $row) {
            $evolution = $evolutionById[$impressionId] ?? [];
            $email = $emailRowsById[$impressionId] ?? null;
            $runId = $this->stringValue($evolution['run_id'] ?? null);

            $groups = $this->groupList([
                'same_conversation' => $this->othersCount($threadCounts, $this->emailValue($email, ['thread_id'])),
                'same_sender' => $this->othersCount($senderCounts, $this->emailValue($email, ['sender', 'from_email'])),
                'same_source' => $this->othersCount($sourceCounts, $this->stringValue($this->rowValue($row, 'source_path'))),
                'dreamed_together' => $this->othersCount($requestRunCounts, $runId),
                'same_output' => $this->stringValue($evolution['packet_id'] ?? null) !== null
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
     * @param  array<string, mixed>  $rowsById
     * @return array<string, int> listing rows per source_path
     */
    private function sourcePathCounts(array $rowsById): array
    {
        $counts = [];

        foreach ($rowsById as $row) {
            $sourcePath = $this->stringValue($this->rowValue($row, 'source_path'));

            if ($sourcePath !== null) {
                $counts[$sourcePath] = ($counts[$sourcePath] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Grouped sidecar email counts for the listing's senders or threads. The
     * first of the candidate columns that exists on the sidecar source is
     * grouped on; a missing source or column yields no counts.
     *
     * @param  list<string>  $values
     * @param  list<string>  $candidateColumns
     * @return array<string, int>
     */
    private function sidecarCounts(array $values, array $candidateColumns): array
    {
        if ($values === []) {
            return [];
        }

        try {
            if (! $this->sourceExists(new Email)) {
                return [];
            }

            $available = $this->columns(Email::class);
            $column = null;

            foreach ($candidateColumns as $candidate) {
                if (in_array($candidate, $available, true)) {
                    $column = $candidate;
                    break;
                }
            }

            if ($column === null) {
                return [];
            }

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
            if (! $this->sourceExists($model)) {
                return [];
            }

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
            $key = $this->stringValue($row->{$column} ?? null);

            if ($key !== null) {
                $counts[$key] = (int) ($row->connection_count ?? 0);
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, object|null>  $emailRowsById
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function emailValues(array $emailRowsById, array $columns): array
    {
        $values = [];

        foreach ($emailRowsById as $email) {
            $value = $this->emailValue($email, $columns);

            if ($value !== null) {
                $values[$value] = true;
            }
        }

        return array_map(strval(...), array_keys($values));
    }

    /**
     * @param  list<string>  $columns
     */
    private function emailValue(?object $email, array $columns): ?string
    {
        if ($email === null) {
            return null;
        }

        foreach ($columns as $column) {
            $value = $this->stringValue($email->{$column} ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function rowValue(mixed $row, string $key): mixed
    {
        if ($row instanceof Model) {
            return $row->getAttribute($key);
        }

        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        return is_object($row) && property_exists($row, $key) ? $row->{$key} : null;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_resource($value)) {
            return null;
        }

        return (string) $value;
    }
}
