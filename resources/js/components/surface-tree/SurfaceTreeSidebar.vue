<template>
    <nav class="surface-tree--sidebar" aria-label="Surface tree sidebar">
        <p v-if="isLoadingRoots" class="surface-tree__muted">Loading...</p>
        <p v-else-if="rootError" class="surface-tree__muted" role="alert">{{ rootError }}</p>

        <ol v-else>
            <SurfaceTreeNode
                v-for="node in visibleRootNodes"
                :key="node.key"
                :node="node"
                :children-by-key="childrenByKey"
                :expanded-node-keys="expandedNodeKeys"
                :loading-node-keys="loadingNodeKeys"
                :node-errors="nodeErrors"
                :selected-node-key="selectedNodeKey"
                :email-filter-mode="emailFilterMode"
                @toggle="toggleNode"
                @load-deeper="loadDeeper"
                @select="selectNode"
            />
        </ol>
    </nav>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { emailFilterChangedEventName, nodeMatchesEmailFilter } from './emailFilters';
import SurfaceTreeNodeComponent from './SurfaceTreeNode.vue';
import type { CachedSurfaceTreeChildren, EmailFilterChangedEvent, EmailFilterMode, SurfaceTreeNode } from './types';

const SurfaceTreeNode = SurfaceTreeNodeComponent;
const depthWindow = 3;

const props = withDefaults(
    defineProps<{
        rootsUrl?: string;
        childrenUrlTemplate?: string;
    }>(),
    {
        rootsUrl: '/surface-tree/nodes',
        childrenUrlTemplate: '/surface-tree/nodes/__NODE_KEY__/children',
    },
);

const rootNodes = ref<SurfaceTreeNode[]>([]);
const childrenByKey = ref<Record<string, SurfaceTreeNode[]>>({});
const expandedNodeKeys = ref<Set<string>>(new Set());
const loadingNodeKeys = ref<Set<string>>(new Set());
const nodeErrors = ref<Record<string, string>>({});
const isLoadingRoots = ref(false);
const rootError = ref<string | null>(null);
const selectedNodeKey = ref<string | null>(null);
const emailFilterMode = ref<EmailFilterMode>('all');

const visibleRootNodes = computed(() => rootNodes.value.filter((node) => nodeMatchesEmailFilter(node, emailFilterMode.value)));

onMounted(() => {
    window.addEventListener(emailFilterChangedEventName, handleEmailFilterChanged);
    fetchRoots();
});

onBeforeUnmount(() => {
    window.removeEventListener(emailFilterChangedEventName, handleEmailFilterChanged);
});

const handleEmailFilterChanged = (event: Event) => {
    const mode = (event as EmailFilterChangedEvent).detail?.mode;

    if (mode === 'all' || mode === 'sensemade' || mode === 'non_sensemade') {
        emailFilterMode.value = mode;
    }
};

const toggleNode = async (node: SurfaceTreeNode) => {
    if (!node.has_children) {
        return;
    }

    const nextExpandedNodeKeys = new Set(expandedNodeKeys.value);

    if (nextExpandedNodeKeys.has(node.key)) {
        nextExpandedNodeKeys.delete(node.key);
        expandedNodeKeys.value = nextExpandedNodeKeys;
        return;
    }

    await loadChildren(node);

    nextExpandedNodeKeys.add(node.key);
    expandedNodeKeys.value = nextExpandedNodeKeys;
};

const loadDeeper = async (node: SurfaceTreeNode) => {
    if (!node.has_children) {
        return;
    }

    await loadChildren(node);

    expandedNodeKeys.value = new Set([...expandedNodeKeys.value, node.key]);
};

const selectNode = (node: SurfaceTreeNode) => {
    selectedNodeKey.value = node.key;

    if (node.type !== 'impression') {
        toggleNode(node);
    }

    // The main content panel is a separate Vue app; selection crosses over via
    // a window event so the sidebar stays free of impression card concerns.
    window.dispatchEvent(new CustomEvent('olo:surface-tree:select', { detail: nodePayload(node) }));
};

const nodePayload = (node: SurfaceTreeNode): SurfaceTreeNode => ({
    key: node.key,
    label: node.label,
    type: node.type,
    domain: node.domain,
    impression_id: node.impression_id ?? null,
    relation: node.relation ?? null,
    depth: node.depth,
    has_children: node.has_children,
    is_terminal_depth: node.is_terminal_depth,
    href: node.href ?? null,
    meta: node.meta,
});

async function fetchRoots() {
    isLoadingRoots.value = true;
    rootError.value = null;

    try {
        const payload = await fetchSurfaceTreeJson(`${props.rootsUrl}?depth_window=${depthWindow}`);
        rootNodes.value = payload.data;
    } catch (error) {
        rootError.value = error instanceof Error ? error.message : 'Surface tree roots could not be loaded.';
    } finally {
        isLoadingRoots.value = false;
    }
}

async function loadChildren(node: SurfaceTreeNode) {
    if (childrenByKey.value[node.key]) {
        return;
    }

    const cachedChildren = readCachedChildren(node);

    if (cachedChildren) {
        childrenByKey.value = {
            ...childrenByKey.value,
            [node.key]: cachedChildren,
        };
        return;
    }

    loadingNodeKeys.value = new Set([...loadingNodeKeys.value, node.key]);
    nodeErrors.value = withoutKey(nodeErrors.value, node.key);

    try {
        const payload = await fetchSurfaceTreeJson(childrenUrl(node.key));

        childrenByKey.value = {
            ...childrenByKey.value,
            [node.key]: payload.data,
        };

        writeCachedChildren(node, payload.data);
    } catch (error) {
        nodeErrors.value = {
            ...nodeErrors.value,
            [node.key]: error instanceof Error ? error.message : 'Children could not be loaded.',
        };
    } finally {
        const nextLoading = new Set(loadingNodeKeys.value);
        nextLoading.delete(node.key);
        loadingNodeKeys.value = nextLoading;
    }
}

async function fetchSurfaceTreeJson(url: string): Promise<{ data: SurfaceTreeNode[] }> {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
    }

    return response.json();
}

function childrenUrl(nodeKey: string): string {
    const url = props.childrenUrlTemplate.replace('__NODE_KEY__', encodeURIComponent(nodeKey));

    return `${url}${url.includes('?') ? '&' : '?'}depth_window=${depthWindow}`;
}

function readCachedChildren(node: SurfaceTreeNode): SurfaceTreeNode[] | null {
    try {
        const cached = localStorage.getItem(cacheKey(node.key));

        if (!cached) {
            return null;
        }

        const payload = JSON.parse(cached) as CachedSurfaceTreeChildren;

        if (!Array.isArray(payload.children) || !payload.expiresAt || new Date(payload.expiresAt) <= new Date()) {
            localStorage.removeItem(cacheKey(node.key));
            return null;
        }

        return payload.children;
    } catch {
        localStorage.removeItem(cacheKey(node.key));
        return null;
    }
}

function writeCachedChildren(node: SurfaceTreeNode, children: SurfaceTreeNode[]) {
    const cachedAt = new Date();
    const expiresAt = new Date(cachedAt.getTime() + cacheTtlMs(node.domain));
    const payload: CachedSurfaceTreeChildren = {
        cachedAt: cachedAt.toISOString(),
        expiresAt: expiresAt.toISOString(),
        children,
    };

    try {
        localStorage.setItem(cacheKey(node.key), JSON.stringify(payload));
    } catch {
        // Cache failures should not prevent the freshly loaded branch from rendering.
    }
}

function cacheKey(nodeKey: string): string {
    return `surface-tree-sidebar:${nodeKey}:depth:${depthWindow}:v1`;
}

function cacheTtlMs(domain: SurfaceTreeNode['domain']): number {
    return {
        filesystem: 5 * 60 * 1000,
        email: 2 * 60 * 1000,
        dreamstate: 60 * 1000,
        camera_lens: 60 * 1000,
    }[domain];
}

function withoutKey(record: Record<string, string>, key: string): Record<string, string> {
    const next = { ...record };
    delete next[key];

    return next;
}
</script>
