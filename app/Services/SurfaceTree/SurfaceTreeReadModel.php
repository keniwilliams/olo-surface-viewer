<?php

namespace App\Services\SurfaceTree;

class SurfaceTreeReadModel
{
    public function __construct(
        private readonly FilesystemTreeTraverser $filesystemTree,
        private readonly EmailTreeTraverser $emailTree,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function roots(int $depthWindow = 3): array
    {
        return array_map(
            fn (SurfaceTreeNode $node): array => $this->normaliseForWindow($node, parentDepth: 0, depthWindow: $depthWindow)->toArray(),
            $this->rootNodes(),
        );
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function childrenFor(string $nodeKey, int $depthWindow = 3): ?array
    {
        $fromDepth = $this->nodeDepth($nodeKey);

        if ($fromDepth === null) {
            return null;
        }

        return array_map(
            fn (SurfaceTreeNode $node): array => $this->normaliseForWindow($node, $fromDepth, $depthWindow)->toArray(),
            $this->traverserChildren($nodeKey, $fromDepth, $depthWindow),
        );
    }

    private function normaliseForWindow(SurfaceTreeNode $node, int $parentDepth, int $depthWindow): SurfaceTreeNode
    {
        $terminalDepth = $parentDepth + $depthWindow;

        return new SurfaceTreeNode(
            key: $node->key,
            label: $node->label,
            type: $node->type,
            domain: $node->domain,
            impressionId: $node->impressionId,
            relation: $node->relation,
            depth: $node->depth,
            hasChildren: $node->hasChildren,
            isTerminalDepth: $node->isTerminalDepth || $node->depth >= $terminalDepth,
            href: $node->href,
            meta: $node->meta,
        );
    }

    /**
     * @return list<SurfaceTreeNode>
     */
    private function rootNodes(): array
    {
        return [
            new SurfaceTreeNode('domain:filesystem', 'Filesystem', 'domain', 'filesystem', null, null, 0, true, false, null),
            new SurfaceTreeNode('domain:email', 'Email', 'domain', 'email', null, null, 0, true, false, null),
            new SurfaceTreeNode('domain:dreamstate', 'Dreamstate', 'domain', 'dreamstate', null, null, 0, false, false, null),
            new SurfaceTreeNode('domain:camera_lens', 'Camera Lens', 'domain', 'camera_lens', null, null, 0, false, false, null),
        ];
    }

    /**
     * @return list<SurfaceTreeNode>
     */
    private function traverserChildren(string $nodeKey, int $fromDepth, int $depthWindow): array
    {
        if ($nodeKey === 'domain:filesystem' || str_starts_with($nodeKey, 'folder:filesystem:')) {
            return $this->filesystemTree->children($nodeKey, $fromDepth, $depthWindow);
        }

        if ($nodeKey === 'domain:email' || str_starts_with($nodeKey, 'sender:email:')) {
            return $this->emailTree->children($nodeKey, $fromDepth, $depthWindow);
        }

        if (str_starts_with($nodeKey, 'impression:') || in_array($nodeKey, ['domain:dreamstate', 'domain:camera_lens'], true)) {
            return [];
        }

        return [];
    }

    private function nodeDepth(string $nodeKey): ?int
    {
        foreach ($this->rootNodes() as $node) {
            if ($node->key === $nodeKey) {
                return $node->depth;
            }
        }

        if (str_starts_with($nodeKey, 'folder:filesystem:')) {
            $decoded = $this->decodeKeyPart(substr($nodeKey, strlen('folder:filesystem:')));

            return $decoded === null ? null : count(explode('/', $decoded));
        }

        if (str_starts_with($nodeKey, 'sender:email:')) {
            return 1;
        }

        if (str_starts_with($nodeKey, 'impression:')) {
            return 2;
        }

        return null;
    }

    private function decodeKeyPart(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }
}
