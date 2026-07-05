export type SurfaceTreeNode = {
    key: string;
    label: string;
    type: 'domain' | 'folder' | 'impression' | 'relationship' | 'record';
    domain: 'filesystem' | 'email' | 'dreamstate' | 'camera_lens';
    impression_id?: string | null;
    relation?: string | null;
    depth: number;
    has_children: boolean;
    is_terminal_depth: boolean;
    href?: string | null;
    meta: Record<string, unknown>;
};

export type SurfaceMainContentState = {
    mode: 'empty' | 'impression_card';
    selectedNodeKey?: string | null;
    impression_id?: string | null;
    payload?: Record<string, unknown>;
};

export type CachedSurfaceTreeChildren = {
    cachedAt: string;
    expiresAt: string;
    children: SurfaceTreeNode[];
};
