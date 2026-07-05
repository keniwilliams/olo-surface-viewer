<?php

namespace App\Services\SurfaceTree;

use App\Services\SurfaceTree\Concerns\BuildsSurfaceTreeNodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FilesystemTreeTraverser implements SurfaceTreeDomainTraverser
{
    use BuildsSurfaceTreeNodes;

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
            $path = $this->value($row, ['source_path', 'path', 'file_path', 'source_ref', 'source_reference']);
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
            ...array_values($folders),
            ...array_values($impressions),
        ];
    }

    /**
     * @return list<object>
     */
    private function filesystemImpressions(): array
    {
        $table = $this->firstExistingTable('impressions', ['sensemade_impressions', 'impressions']);

        if ($table === null) {
            return [];
        }

        try {
            $columns = Schema::connection('impressions')->getColumnListing($table);
            $query = DB::connection('impressions')->table($table)->select($columns)->limit(250);

            if (in_array('domain', $columns, true)) {
                $query->where('domain', 'filesystem');
            }

            foreach (['observed_at', 'sensemade_at', 'created_at'] as $orderColumn) {
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

    private function firstExistingTable(string $connection, array $tables): ?string
    {
        foreach ($tables as $table) {
            try {
                if (Schema::connection($connection)->hasTable($table)) {
                    return $table;
                }
            } catch (Throwable) {
                return null;
            }
        }

        return null;
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
            $segments = $this->pathSegments($this->value($row, ['source_path', 'path', 'file_path', 'source_ref', 'source_reference']));

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

        $sourcePath = $this->value($row, ['source_path', 'path', 'file_path', 'source_ref', 'source_reference']);

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
}
