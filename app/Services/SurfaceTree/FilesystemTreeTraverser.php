<?php

namespace App\Services\SurfaceTree;

use App\Models\Impressions\ImpressionDreamstateFeed;
use App\Services\SurfaceTree\Concerns\BuildsSurfaceTreeNodes;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Filesystem domain traverser: groups the path-bearing rows of the canonical
 * impressions_dreamstate_feed view into a folder tree and serves the raw
 * corpus lookup for the corpus endpoint. All reads go through the
 * ImpressionDreamstateFeed model; a missing or unreadable feed yields an
 * empty tree rather than an error.
 */
class FilesystemTreeTraverser implements SurfaceTreeDomainTraverser
{
    use BuildsSurfaceTreeNodes;

    private const int PATH_ROW_WINDOW = 500;

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

        $rows = $this->pathBearingRows();

        if ($rows->isEmpty()) {
            return [];
        }

        $childDepth = $fromDepth + 1;
        $folders = [];
        $impressions = [];

        foreach ($rows as $row) {
            $segments = $this->pathSegments($row->source_path);

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

    public function rawCorpus(string $impressionId): ?string
    {
        try {
            return ImpressionDreamstateFeed::findForSurfaceTreeCorpus($impressionId)?->decodedRawCorpus();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<int, ImpressionDreamstateFeed>
     */
    private function pathBearingRows(): Collection
    {
        try {
            return ImpressionDreamstateFeed::latestPathBearingForSurfaceTree(self::PATH_ROW_WINDOW);
        } catch (Throwable) {
            return new Collection;
        }
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
     * @param  Collection<int, ImpressionDreamstateFeed>  $rows
     * @param  list<string>  $prefix
     */
    private function hasFilesystemChildren(Collection $rows, array $prefix): bool
    {
        foreach ($rows as $row) {
            $segments = $this->pathSegments($row->source_path);

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

    private function filesystemImpressionNode(ImpressionDreamstateFeed $row, int $depth, bool $isTerminalDepth): ?SurfaceTreeNode
    {
        $impressionId = $this->text($row->impression_id);

        if ($impressionId === null) {
            return null;
        }

        $sourcePath = $this->text($row->source_path);

        return $this->impressionNode(
            impressionId: $impressionId,
            label: basename(str_replace('\\', '/', $sourcePath ?? '')) ?: 'Filesystem impression',
            domain: 'filesystem',
            depth: $depth,
            isTerminalDepth: $isTerminalDepth,
            meta: [
                'kind' => $this->text($row->kind) ?? $this->text($row->memory_kind),
                'status' => $this->text($row->process_status),
                'observed_at' => $row->observed_at === null ? null : (string) $row->observed_at,
                'source_path' => $sourcePath,
            ],
        );
    }
}
