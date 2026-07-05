<template>
    <section aria-label="Surface tree browser">
        <aside aria-label="Surface tree navigation">
            <ol>
                <SurfaceTreeNode
                    v-for="node in rootNodes"
                    :key="node.key"
                    :node="node"
                    :children-by-key="childrenByKey"
                    :expanded-node-keys="expandedNodeKeys"
                    :selected-node-key="selectedNodeKey"
                    @toggle="toggleNode"
                    @select="selectNode"
                />
            </ol>
        </aside>

        <SurfaceTreeMainContentHost :state="mainContentState" />
    </section>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import SurfaceTreeMainContentHost from './SurfaceTreeMainContentHost.vue';
import SurfaceTreeNodeComponent from './SurfaceTreeNode.vue';
import type { SurfaceMainContentState, SurfaceTreeNode } from './types';

const SurfaceTreeNode = SurfaceTreeNodeComponent;

const rootNodes = ref<SurfaceTreeNode[]>([
    {
        key: 'domain:filesystem',
        label: 'Filesystem',
        type: 'domain',
        domain: 'filesystem',
        impression_id: null,
        relation: null,
        depth: 0,
        has_children: true,
        is_terminal_depth: false,
        href: null,
        meta: {},
    },
    {
        key: 'domain:email',
        label: 'Email',
        type: 'domain',
        domain: 'email',
        impression_id: null,
        relation: null,
        depth: 0,
        has_children: true,
        is_terminal_depth: false,
        href: null,
        meta: {},
    },
    {
        key: 'domain:dreamstate',
        label: 'Dreamstate',
        type: 'domain',
        domain: 'dreamstate',
        impression_id: null,
        relation: null,
        depth: 0,
        has_children: true,
        is_terminal_depth: false,
        href: null,
        meta: {},
    },
    {
        key: 'domain:camera_lens',
        label: 'Camera Lens',
        type: 'domain',
        domain: 'camera_lens',
        impression_id: null,
        relation: null,
        depth: 0,
        has_children: true,
        is_terminal_depth: false,
        href: null,
        meta: {},
    },
]);

const childrenByKey = ref<Record<string, SurfaceTreeNode[]>>({
    'domain:filesystem': [
        {
            key: 'impression:filesystem:first',
            label: 'Filesystem impression',
            type: 'impression',
            domain: 'filesystem',
            impression_id: 'filesystem:first',
            relation: null,
            depth: 1,
            has_children: false,
            is_terminal_depth: true,
            href: null,
            meta: {
                kind: 'file',
                status: 'available',
                source_path: 'filesystem:first',
            },
        },
    ],
    'domain:email': [
        {
            key: 'impression:email:first',
            label: 'Email impression',
            type: 'impression',
            domain: 'email',
            impression_id: 'email:first',
            relation: null,
            depth: 1,
            has_children: false,
            is_terminal_depth: true,
            href: null,
            meta: {
                kind: 'message',
                status: 'available',
                source_ref: 'email:first',
            },
        },
    ],
    'domain:dreamstate': [
        {
            key: 'impression:dreamstate:first',
            label: 'Dreamstate impression',
            type: 'impression',
            domain: 'dreamstate',
            impression_id: 'dreamstate:first',
            relation: null,
            depth: 1,
            has_children: false,
            is_terminal_depth: true,
            href: null,
            meta: {
                memory_kind: 'dream',
                process_status: 'available',
                source_ref: 'dreamstate:first',
            },
        },
    ],
    'domain:camera_lens': [
        {
            key: 'impression:camera_lens:first',
            label: 'Camera Lens impression',
            type: 'impression',
            domain: 'camera_lens',
            impression_id: 'camera_lens:first',
            relation: null,
            depth: 1,
            has_children: false,
            is_terminal_depth: true,
            href: null,
            meta: {
                kind: 'capture',
                status: 'available',
                source_ref: 'camera_lens:first',
            },
        },
    ],
});
const expandedNodeKeys = ref<Set<string>>(new Set());
const selectedNodeKey = ref<string | null>(null);
const mainContentState = ref<SurfaceMainContentState>({ mode: 'empty' });

const toggleNode = (nodeKey: string) => {
    const nextExpandedNodeKeys = new Set(expandedNodeKeys.value);

    if (nextExpandedNodeKeys.has(nodeKey)) {
        nextExpandedNodeKeys.delete(nodeKey);
    } else {
        nextExpandedNodeKeys.add(nodeKey);
    }

    expandedNodeKeys.value = nextExpandedNodeKeys;
};

const selectNode = (node: SurfaceTreeNode) => {
    selectedNodeKey.value = node.key;

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
</script>
