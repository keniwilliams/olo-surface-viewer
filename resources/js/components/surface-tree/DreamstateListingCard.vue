<template>
    <article class="surface-tree__card" aria-label="Dreamstate listing">
        <h2 class="surface-tree__card-title">Dreamstate</h2>
        <p class="surface-tree__dreamstate-intro">
            What the system noticed, connected, and transformed — most recent first.
        </p>

        <section class="surface-tree__dreamstate-list" aria-label="Dreamstate impressions">
            <p v-if="isLoading" class="surface-tree__corpus-muted">Loading impressions...</p>
            <p v-else-if="loadError" class="surface-tree__corpus-muted" role="alert">{{ loadError }}</p>
            <p v-else-if="impressions.length === 0" class="surface-tree__corpus-muted">No Dreamstate impressions available.</p>

            <template v-else>
                <DreamstateImpressionCard
                    v-for="impression in impressions"
                    :key="impression.key"
                    :state="impressionState(impression)"
                />
            </template>
        </section>
    </article>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import DreamstateImpressionCard from './DreamstateImpressionCard.vue';
import { nodeToDreamstatePayload } from './dreamstateDisplay';
import type { SurfaceMainContentState, SurfaceTreeNode } from './types';

const props = defineProps<{
    state: SurfaceMainContentState;
}>();

const selectedNodeKey = computed(() => props.state.selectedNodeKey ?? 'domain:dreamstate');

const impressions = ref<SurfaceTreeNode[]>([]);
const isLoading = ref(false);
const loadError = ref<string | null>(null);

watch(
    () => selectedNodeKey.value,
    (nodeKey) => {
        impressions.value = [];
        loadError.value = null;

        if (nodeKey) {
            fetchImpressions(nodeKey);
        }
    },
    { immediate: true },
);

async function fetchImpressions(nodeKey: string) {
    isLoading.value = true;
    loadError.value = null;

    try {
        const response = await fetch(`/surface-tree/nodes/${encodeURIComponent(nodeKey)}/children?depth_window=3`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            if (selectedNodeKey.value === nodeKey) {
                loadError.value = `Request failed: ${response.status}`;
            }

            return;
        }

        const responsePayload = (await response.json()) as { data?: SurfaceTreeNode[] };

        if (selectedNodeKey.value !== nodeKey) {
            return;
        }

        impressions.value = Array.isArray(responsePayload.data)
            ? responsePayload.data.filter((node) => node.type === 'impression')
            : [];
    } catch (error) {
        if (selectedNodeKey.value !== nodeKey) {
            return;
        }

        loadError.value = error instanceof Error
            ? error.message
            : 'Impressions could not be loaded.';
    } finally {
        if (selectedNodeKey.value === nodeKey) {
            isLoading.value = false;
        }
    }
}

function impressionState(impression: SurfaceTreeNode): SurfaceMainContentState {
    return {
        mode: 'dreamstate_impression_card',
        selectedNodeKey: impression.key,
        impression_id: impression.impression_id ?? null,
        payload: nodeToDreamstatePayload(impression),
    };
}
</script>
