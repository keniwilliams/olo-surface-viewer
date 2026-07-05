<?php

namespace App\Services\SurfaceTree\Concerns;

use App\Services\SurfaceTree\SurfaceTreeNode;
use Illuminate\Support\Str;

trait BuildsSurfaceTreeNodes
{
    /**
     * @param  array<string, mixed>  $meta
     */
    private function folderNode(
        string $key,
        string $label,
        string $domain,
        int $depth,
        bool $hasChildren,
        bool $isTerminalDepth,
        ?string $relation = 'contains',
        array $meta = [],
    ): SurfaceTreeNode {
        return new SurfaceTreeNode(
            key: $key,
            label: $label,
            type: 'folder',
            domain: $domain,
            impressionId: null,
            relation: $relation,
            depth: $depth,
            hasChildren: $hasChildren,
            isTerminalDepth: $isTerminalDepth,
            href: null,
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function impressionNode(
        string $impressionId,
        string $label,
        string $domain,
        int $depth,
        bool $isTerminalDepth,
        array $meta = [],
    ): SurfaceTreeNode {
        return new SurfaceTreeNode(
            key: 'impression:'.$impressionId,
            label: $label,
            type: 'impression',
            domain: $domain,
            impressionId: $impressionId,
            relation: null,
            depth: $depth,
            hasChildren: false,
            isTerminalDepth: $isTerminalDepth,
            href: '/impressions/'.rawurlencode($impressionId),
            meta: array_filter($meta, fn (mixed $value): bool => $value !== null && $value !== ''),
        );
    }

    private function encodeKeyPart(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decodeKeyPart(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }

    private function value(mixed $row, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_object($row) || ! property_exists($row, $candidate)) {
                continue;
            }

            $value = $row->{$candidate};

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function titleFromIdentifier(?string $identifier, string $fallback): string
    {
        if ($identifier === null || $identifier === '') {
            return $fallback;
        }

        return Str::limit($identifier, 80, '');
    }

    private function reachesTerminalDepth(int $depth, int $fromDepth, int $depthWindow): bool
    {
        return $depth >= $fromDepth + $depthWindow;
    }
}
