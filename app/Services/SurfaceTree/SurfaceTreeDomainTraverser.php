<?php

namespace App\Services\SurfaceTree;

interface SurfaceTreeDomainTraverser
{
    /**
     * @return list<SurfaceTreeNode>
     */
    public function children(string $nodeKey, int $fromDepth, int $depthWindow): array;
}
