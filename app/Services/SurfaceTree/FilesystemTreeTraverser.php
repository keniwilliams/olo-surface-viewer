<?php

namespace App\Services\SurfaceTree;

use App\Services\SurfaceTree\Concerns\BuildsSurfaceTreeNodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FilesystemTreeTraverser implements SurfaceTreeDomainTraverser
{
    use BuildsSurfaceTreeNodes;

    const array CANDIDATES = ['source_path', 'source_ref', 'canonical_uri', 'path', 'file_path', 'source_reference', 'raw_corpus'];

    /**
     * @return list<SurfaceTreeNode>
     */
    public function children(string $nodeKey, int $fromDepth, int $depthWindow): array
    {
        if (str_starts_with($nodeKey, 'impression:')) {
            return [];
        }

        $prefix = $this->pathPrefixFromNodeKey($nodeKey);

        if ($prefix === null) {
            return [];
        }

        $rows = $this->filesystemImpressions();

        if ($rows === []) {
            return [];
        }

        $childDepth = $fromDepth + 1;
        $folders = [];
        $impressions = [];

        foreach ($rows as $row) {
            $candidates = self::CANDIDATES;
            $path = $this->value($row, $candidates);
            $segments = $this->pathSegments($path);

            if ($segments === [] || ! $this->startsWithSegments($segments, $prefix)) {
                continue;
            }

            $remaining = array_slice($segments, count($prefix));

            if (count($remaining) > 1) {
                $folderPrefix = [...$prefix, $remaining[0]];
                $folderKey = $this->filesystemFolderKey($folderPrefix);

                $folders[$folderKey] = $this->folderNode(
                    key: $folderKey,
                    label: $remaining[0],
                    domain: 'filesystem',
                    depth: $childDepth,
                    hasChildren: $this->hasFilesystemChildren($rows, $folderPrefix),
                    isTerminalDepth: $this->reachesTerminalDepth($childDepth, $fromDepth, $depthWindow),
                    meta: [
                        'source_path' => implode(DIRECTORY_SEPARATOR, $folderPrefix),
                    ],
                );

                continue;
            }

            $impression = $this->filesystemImpressionNode($row, $childDepth, $this->reachesTerminalDepth($childDepth, $fromDepth, $depthWindow));

            if ($impression) {
                $impressions[$impression->key] = $impression;
            }
        }

        return [
            ...$this->sortNodesByLabel(array_values($folders)),
            ...$this->sortNodesByLabel(array_values($impressions)),
        ];
    }

    /**
     * @param  list<SurfaceTreeNode>  $nodes
     * @return list<SurfaceTreeNode>
     */
    private function sortNodesByLabel(array $nodes): array
    {
        usort(
            $nodes,
            fn (SurfaceTreeNode $a, SurfaceTreeNode $b): int => strnatcasecmp($a->label, $b->label),
        );

        return $nodes;
    }

    /**
     * @return list<object>
     */
    private function filesystemImpressions(): array
    {
        $table = $this->firstExistingTable('impressions', [
            'impressions_dreamstate_feed',
            'sensemade_impressions',
            'impressions',
        ]);

        if ($table === null) {
            return [];
        }

        try {
            $columns = Schema::connection('impressions')->getColumnListing($table);

            $query = DB::connection('impressions')
                ->table($table)
                ->select($columns);

            if (in_array('source_path', $columns, true)) {
                $query
                    ->whereNotNull('source_path')
                    ->where('source_path', '<>', '');
            }

            foreach (['observed_at', 'sensemade_at', 'created_at'] as $orderColumn) {
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

    private function firstExistingTable(string $connection, array $tables): ?string
    {
        foreach ($tables as $table) {
            if ($this->tableOrViewExists($connection, $table)) {
                return $table;
            }
        }

        return null;
    }

    private function tableOrViewExists(string $connection, string $table): bool
    {
        try {
            if (Schema::connection($connection)->hasTable($table)) {
                return true;
            }
        } catch (Throwable) {
            // Fall through to the driver-level probe below.
        }

        // Schema::hasTable() can miss Postgres views; to_regclass resolves both.
        try {
            if (DB::connection($connection)->getDriverName() !== 'pgsql') {
                return false;
            }

            $result = DB::connection($connection)
                ->selectOne('select to_regclass(?) as relation', ['public.'.$table]);

            return ($result->relation ?? null) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>|null
     */
    private function pathPrefixFromNodeKey(string $nodeKey): ?array
    {
        if ($nodeKey === 'domain:filesystem') {
            return [];
        }

        if (! str_starts_with($nodeKey, 'folder:filesystem:')) {
            return null;
        }

        $decoded = $this->decodeKeyPart(substr($nodeKey, strlen('folder:filesystem:')));

        return $decoded === null || $decoded === '' ? null : explode('/', $decoded);
    }

    /**
     * @return list<string>
     */
    private function pathSegments(?string $path): array
    {
        if ($path === null || trim($path) === '') {
            return [];
        }

        $normalised = str_replace('\\', '/', trim($path));

        return array_values(array_filter(explode('/', $normalised), fn (string $segment): bool => $segment !== ''));
    }

    /**
     * @param  list<string>  $segments
     * @param  list<string>  $prefix
     */
    private function startsWithSegments(array $segments, array $prefix): bool
    {
        foreach ($prefix as $index => $segment) {
            if (($segments[$index] ?? null) !== $segment) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<object>  $rows
     * @param  list<string>  $prefix
     */
    private function hasFilesystemChildren(array $rows, array $prefix): bool
    {
        foreach ($rows as $row) {
            $segments = $this->pathSegments($this->value($row, self::CANDIDATES));

            if ($this->startsWithSegments($segments, $prefix) && count($segments) > count($prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $prefix
     */
    private function filesystemFolderKey(array $prefix): string
    {
        return 'folder:filesystem:'.$this->encodeKeyPart(implode('/', $prefix));
    }

    private function filesystemImpressionNode(object $row, int $depth, bool $isTerminalDepth): ?SurfaceTreeNode
    {
        $impressionId = $this->value($row, ['impression_id', 'uuid', 'id']);

        if ($impressionId === null) {
            return null;
        }

        $sourcePath = $this->value($row, self::CANDIDATES);


        return $this->impressionNode(
            impressionId: $impressionId,
            label: $this->value($row, ['label', 'title', 'name']) ?? basename(str_replace('\\', '/', $sourcePath ?? '')) ?: 'Filesystem impression',
            domain: 'filesystem',
            depth: $depth,
            isTerminalDepth: $isTerminalDepth,
            meta: [
                'kind' => $this->value($row, ['kind', 'memory_kind']),
                'status' => $this->value($row, ['status', 'process_status']),
                'observed_at' => $this->value($row, ['observed_at', 'sensemade_at', 'created_at']),
                'source_path' => $sourcePath,
            ],
        );
    }

    public function rawCorpus(string $impressionId): ?string
    {
        $table = $this->firstExistingTable('impressions', [
            'impressions_dreamstate_feed',
            'sensemade_impressions',
            'impressions',
        ]);

        if ($table === null) {
            return null;
        }

        try {
            $columns = Schema::connection('impressions')->getColumnListing($table);

            if (! in_array('raw_corpus', $columns, true)) {
                return null;
            }

            $idColumn = null;

            foreach (['impression_id', 'uuid', 'id'] as $candidate) {
                if (in_array($candidate, $columns, true)) {
                    $idColumn = $candidate;
                    break;
                }
            }

            if ($idColumn === null) {
                return null;
            }

            $row = DB::connection('impressions')
                ->table($table)
                ->select($columns)
                ->where($idColumn, $impressionId)
                ->first();

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
    private function rawCorpusText(object $row): ?string
    {
        $raw = property_exists($row, 'raw_corpus') ? $row->raw_corpus : null;

        if (is_resource($raw)) {
            $raw = stream_get_contents($raw);
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $encoding = $this->value($row, ['raw_corpus_encoding']);

        if (($encoding === null || $encoding === 'utf8') && mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        return base64_encode($raw);
    }
}
