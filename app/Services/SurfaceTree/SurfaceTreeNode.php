<?php

namespace App\Services\SurfaceTree;

final readonly class SurfaceTreeNode
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public string $domain,
        public ?string $impressionId,
        public ?string $relation,
        public int $depth,
        public bool $hasChildren,
        public bool $isTerminalDepth,
        public ?string $href,
        public array $meta = [],
    ) {}

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     type: string,
     *     domain: string,
     *     impression_id: string|null,
     *     relation: string|null,
     *     depth: int,
     *     has_children: bool,
     *     is_terminal_depth: bool,
     *     href: string|null,
     *     meta: array<string, mixed>|object
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'domain' => $this->domain,
            'impression_id' => $this->impressionId,
            'relation' => $this->relation,
            'depth' => $this->depth,
            'has_children' => $this->hasChildren,
            'is_terminal_depth' => $this->isTerminalDepth,
            'href' => $this->href,
            'meta' => $this->meta === [] ? (object) [] : $this->meta,
        ];
    }
}
