<template>
    <li class="surface-tree__node">
        <div class="surface-tree__row">
            <button
                v-if="node.has_children"
                class="surface-tree__toggle"
                type="button"
                :aria-expanded="expanded"
                :aria-controls="childrenId"
                @click="emit('toggle', node)"
            >
                {{ expanded ? 'v' : '>' }}
            </button>
            <span v-else class="surface-tree__toggle-placeholder" aria-hidden="true"></span>

            <button
                class="surface-tree__label"
                type="button"
                :aria-current="selected ? 'true' : undefined"
                @click="emit('select', node)"
            >
                <span>{{ node.label }}</span>
                <small class="surface-tree__badge">{{ node.type }}</small>
            </button>
        </div>

        <p v-if="loading" class="surface-tree__muted">
            Loading...
        </p>

        <p v-if="error" class="surface-tree__muted" role="alert">
            {{ error }}
        </p>

        <p v-if="expanded && !loading && !error && node.has_children && childNodes.length === 0" :id="childrenId" class="surface-tree__muted">
            No children loaded.
        </p>

        <p v-if="node.is_terminal_depth && node.has_children && !expanded" class="surface-tree__row">
            <span class="surface-tree__toggle-placeholder" aria-hidden="true"></span>
            <button class="surface-tree__label" type="button" @click="emit('load-deeper', node)">
                Load deeper
            </button>
        </p>

        <ol v-else-if="expanded && childNodes.length > 0" :id="childrenId" class="surface-tree__children">
            <SurfaceTreeNode
                v-for="childNode in childNodes"
                :key="childNode.key"
                :node="childNode"
                :children-by-key="childrenByKey"
                :expanded-node-keys="expandedNodeKeys"
                :loading-node-keys="loadingNodeKeys"
                :node-errors="nodeErrors"
                :selected-node-key="selectedNodeKey"
                @toggle="emit('toggle', $event)"
                @load-deeper="emit('load-deeper', $event)"
                @select="emit('select', $event)"
            />
        </ol>
    </li>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { SurfaceTreeNode as SurfaceTreeNodeData } from './types';

defineOptions({
    name: 'SurfaceTreeNode',
});

const props = defineProps<{
    node: SurfaceTreeNodeData;
    childrenByKey: Record<string, SurfaceTreeNodeData[]>;
    expandedNodeKeys: Set<string>;
    loadingNodeKeys: Set<string>;
    nodeErrors: Record<string, string>;
    selectedNodeKey: string | null;
}>();

const emit = defineEmits<{
    toggle: [node: SurfaceTreeNodeData];
    'load-deeper': [node: SurfaceTreeNodeData];
    select: [node: SurfaceTreeNodeData];
}>();

const expanded = computed(() => props.expandedNodeKeys.has(props.node.key));
const loading = computed(() => props.loadingNodeKeys.has(props.node.key));
const error = computed(() => props.nodeErrors[props.node.key] ?? null);
const selected = computed(() => props.selectedNodeKey === props.node.key);
const childNodes = computed(() => props.childrenByKey[props.node.key] ?? []);
const childrenId = computed(() => `surface-tree-children-${props.node.key.replace(/[^A-Za-z0-9_-]/g, '-')}`);
</script>
