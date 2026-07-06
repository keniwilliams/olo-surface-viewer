<template>
    <section class="surface-tree" aria-label="Surface tree browser">
        <div class="surface-tree__main">
            <SurfaceTreeMainContentHost :state="mainContentState" />
        </div>
    </section>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import SurfaceTreeMainContentHost from './SurfaceTreeMainContentHost.vue';
import type { SurfaceMainContentState, SurfaceTreeNode } from './types';

const mainContentState = ref<SurfaceMainContentState>({ mode: 'empty' });

const handleSurfaceTreeSelect = (event: Event) => {
    const node = (event as CustomEvent<SurfaceTreeNode>).detail;

    if (!node || typeof node.key !== 'string') {
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

onMounted(() => {
    window.addEventListener('olo:surface-tree:select', handleSurfaceTreeSelect);
});

onBeforeUnmount(() => {
    window.removeEventListener('olo:surface-tree:select', handleSurfaceTreeSelect);
});
</script>
