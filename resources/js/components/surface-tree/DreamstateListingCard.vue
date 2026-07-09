<template>
    <article class="surface-tree__card" aria-label="Dreamstate listing">
        <h2 class="surface-tree__card-title">Dreamstate</h2>
        <p class="surface-tree__dreamstate-intro">
            What the system noticed, connected, and transformed — most recent first.
        </p>

        <div class="surface-tree__dreamstate-lenses" role="group" aria-label="Dreamstate lenses">
            <button
                v-for="option in lensOptions"
                :key="option.lens"
                type="button"
                :class="[
                    'surface-tree__dreamstate-lens',
                    lens === option.lens ? 'surface-tree__dreamstate-lens--active' : '',
                ]"
                :aria-pressed="lens === option.lens"
                @click="lens = option.lens"
            >{{ option.label }}</button>
        </div>

        <section class="surface-tree__dreamstate-list" aria-label="Dreamstate impressions">
            <p v-if="isLoading" class="surface-tree__corpus-muted">Loading impressions...</p>
            <p v-else-if="loadError" class="surface-tree__corpus-muted" role="alert">{{ loadError }}</p>
            <p v-else-if="impressions.length === 0" class="surface-tree__corpus-muted">No Dreamstate impressions available.</p>

            <template v-else>
                <section
                    v-for="group in lensGroups"
                    :key="group.key"
                    class="surface-tree__dreamstate-lens-group"
                    :aria-label="group.label"
                >
                    <h3 class="surface-tree__dreamstate-lens-group-title">
                        {{ group.label }}
                        <span class="surface-tree__dreamstate-lens-group-count">{{ group.nodes.length }}</span>
                    </h3>

                    <DreamstateImpressionCard
                        v-for="impression in group.nodes"
                        :key="impression.key"
                        :state="impressionState(impression)"
                    />
                </section>
            </template>
        </section>
    </article>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import DreamstateImpressionCard from './DreamstateImpressionCard.vue';
import {
    aboutLensGroups,
    connectionLensGroups,
    evolutionLensGroups,
    nodeToDreamstatePayload,
    type DreamstateLens,
} from './dreamstateDisplay';
import type { SurfaceMainContentState, SurfaceTreeNode } from './types';

const props = defineProps<{
    state: SurfaceMainContentState;
}>();

const selectedNodeKey = computed(() => props.state.selectedNodeKey ?? 'domain:dreamstate');

const impressions = ref<SurfaceTreeNode[]>([]);
const isLoading = ref(false);
const loadError = ref<string | null>(null);

// The page is structured by human lenses, with About as the front door.
const lens = ref<DreamstateLens>('about');

const lensOptions: { lens: DreamstateLens; label: string }[] = [
    { lens: 'about', label: 'About' },
    { lens: 'evolution', label: 'Evolution' },
    { lens: 'connections', label: 'Connections' },
];

const lensGroups = computed(() => {
    switch (lens.value) {
        case 'evolution':
            return evolutionLensGroups(impressions.value);
        case 'connections':
            return connectionLensGroups(impressions.value);
        default:
            return aboutLensGroups(impressions.value);
    }
});

watch(
    () => selectedNodeKey.value,
    (nodeKey) => {
        impressions.value = [];
        loadError.value = null;
        lens.value = 'about';

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
