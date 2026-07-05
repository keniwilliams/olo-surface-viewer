<template>
    <li class="surface-tree__node">
        <div class="surface-tree__row">
            <button
                v-if="node.has_children"
                class="surface-tree__toggle"
                type="button"
                :aria-expanded="expanded"
                :aria-controls="childrenId"
                @click="emit('toggle', node.key)"
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

        <p v-if="expanded && node.has_children && childNodes.length === 0" :id="childrenId" class="surface-tree__muted">
            No children loaded.
        </p>

        <ol v-else-if="expanded && childNodes.length > 0" :id="childrenId" class="surface-tree__children">
            <SurfaceTreeNode
                v-for="childNode in childNodes"
                :key="childNode.key"
                :node="childNode"
                :children-by-key="childrenByKey"
                :expanded-node-keys="expandedNodeKeys"
                :selected-node-key="selectedNodeKey"
                @toggle="emit('toggle', $event)"
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
    selectedNodeKey: string | null;
}>();

const emit = defineEmits<{
    toggle: [nodeKey: string];
    select: [node: SurfaceTreeNodeData];
}>();

const expanded = computed(() => props.expandedNodeKeys.has(props.node.key));
const selected = computed(() => props.selectedNodeKey === props.node.key);
const childNodes = computed(() => props.childrenByKey[props.node.key] ?? []);
const childrenId = computed(() => `surface-tree-children-${props.node.key.replace(/[^A-Za-z0-9_-]/g, '-')}`);
</script>
