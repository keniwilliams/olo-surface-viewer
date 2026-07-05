<template>
    <section aria-label="Surface tree main content">
        <ImpressionCard v-if="state.mode === 'impression_card'" :state="state" />

        <p v-else-if="state.selectedNodeKey">
            Select an impression to open a card.
        </p>

        <p v-else>
            Select a surface tree node.
        </p>
    </section>
</template>

<script setup lang="ts">
import ImpressionCard from './ImpressionCard.vue';
import type { SurfaceMainContentState } from './types';

defineProps<{
    state: SurfaceMainContentState;
}>();
</script>
