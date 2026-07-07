<template>
    <section class="surface-tree" aria-label="Surface tree browser">
        <div class="surface-tree__toolbar" aria-label="Email display controls">
            <label class="surface-tree__filter-control">
                <span class="surface-tree__filter-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M4 5h16l-6 7v5l-4 2v-7L4 5z" />
                    </svg>
                </span>
                <span class="surface-tree__filter-label">Filter</span>
                <select v-model="emailFilterMode" class="surface-tree__filter-select" aria-label="Email filter">
                    <option value="all">Show all</option>
                    <option value="sensemade">Sensemade</option>
                    <option value="non_sensemade">Non sensemade</option>
                </select>
            </label>
        </div>

        <div class="surface-tree__main">
            <SurfaceTreeMainContentHost :state="mainContentState" :email-filter-mode="emailFilterMode" />
        </div>
    </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { emailFilterChangedEventName, nodeMatchesEmailFilter } from './emailFilters';
import SurfaceTreeMainContentHost from './SurfaceTreeMainContentHost.vue';
import type { EmailFilterMode, SurfaceMainContentState, SurfaceTreeNode } from './types';

const mainContentState = ref<SurfaceMainContentState>({ mode: 'empty' });
const emailFilterMode = ref<EmailFilterMode>('all');

const selectedNode = computed(() => {
    const payload = mainContentState.value.payload;

    return payload ? surfaceTreeNodeFromPayload(payload) : null;
});

const handleSurfaceTreeSelect = (event: Event) => {
    const node = (event as CustomEvent<SurfaceTreeNode>).detail;

    if (!node || typeof node.key !== 'string') {
        return;
    }

    if (!nodeMatchesEmailFilter(node, emailFilterMode.value)) {
        mainContentState.value = {
            mode: 'empty',
            selectedNodeKey: node.key,
            payload: nodePayload(node),
        };

        return;
    }

    if (node.type === 'folder' && node.domain === 'email' && node.relation === 'from_sender') {
        mainContentState.value = {
            mode: 'email_sender_card',
            selectedNodeKey: node.key,
            payload: nodePayload(node),
        };

        return;
    }

    if (node.type === 'record' && node.domain === 'email' && node.relation === 'email_listing') {
        mainContentState.value = {
            mode: 'email_record_card',
            selectedNodeKey: node.key,
            payload: nodePayload(node),
        };

        return;
    }

    if (node.type !== 'impression') {
        mainContentState.value = {
            mode: 'empty',
            selectedNodeKey: node.key,
            payload: nodePayload(node),
        };

        return;
    }

    mainContentState.value = {
        mode: 'impression_card',
        selectedNodeKey: node.key,
        impression_id: node.impression_id ?? null,
        payload: nodePayload(node),
    };
};

const nodePayload = (node: SurfaceTreeNode): Record<string, unknown> => ({
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

const surfaceTreeNodeFromPayload = (payload: Record<string, unknown>): SurfaceTreeNode | null => {
    const key = payload.key;
    const label = payload.label;
    const type = payload.type;
    const domain = payload.domain;
    const depth = payload.depth;
    const hasChildren = payload.has_children;
    const isTerminalDepth = payload.is_terminal_depth;
    const meta = payload.meta;

    if (
        typeof key !== 'string'
        || typeof label !== 'string'
        || typeof type !== 'string'
        || typeof domain !== 'string'
        || typeof depth !== 'number'
        || typeof hasChildren !== 'boolean'
        || typeof isTerminalDepth !== 'boolean'
        || !meta
        || typeof meta !== 'object'
        || Array.isArray(meta)
    ) {
        return null;
    }

    return {
        key,
        label,
        type: type as SurfaceTreeNode['type'],
        domain: domain as SurfaceTreeNode['domain'],
        impression_id: typeof payload.impression_id === 'string' ? payload.impression_id : null,
        relation: typeof payload.relation === 'string' ? payload.relation : null,
        depth,
        has_children: hasChildren,
        is_terminal_depth: isTerminalDepth,
        href: typeof payload.href === 'string' ? payload.href : null,
        meta: meta as Record<string, unknown>,
    };
};

watch(emailFilterMode, (mode) => {
    window.dispatchEvent(new CustomEvent(emailFilterChangedEventName, { detail: { mode } }));

    if (selectedNode.value && !nodeMatchesEmailFilter(selectedNode.value, mode)) {
        mainContentState.value = {
            mode: 'empty',
            selectedNodeKey: selectedNode.value.key,
            payload: nodePayload(selectedNode.value),
        };
    }
});

onMounted(() => {
    window.addEventListener('olo:surface-tree:select', handleSurfaceTreeSelect);
    window.dispatchEvent(new CustomEvent(emailFilterChangedEventName, { detail: { mode: emailFilterMode.value } }));
});

onBeforeUnmount(() => {
    window.removeEventListener('olo:surface-tree:select', handleSurfaceTreeSelect);
});
</script>

