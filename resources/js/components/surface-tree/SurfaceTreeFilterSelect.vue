<template>
    <label class="surface-tree__filter-control">
        <span class="surface-tree__filter-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
                <path d="M4 5h16l-6 7v5l-4 2v-7L4 5z" />
            </svg>
        </span>
        <select v-model="emailFilterMode" id="surface-tree__filter-select" class="surface-tree__filter-select" aria-label="Email filter">
            <option value="all">Show all</option>
            <option value="sensemade">Sensemade</option>
            <option value="non_sensemade">Non sensemade</option>
        </select>
    </label>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { emailFilterChangedEventName } from './emailFilters';
import type { EmailFilterMode } from './types';

const emailFilterMode = ref<EmailFilterMode>('all');

watch(emailFilterMode, (mode) => {
    window.dispatchEvent(new CustomEvent(emailFilterChangedEventName, { detail: { mode } }));
});

onMounted(() => {
    window.dispatchEvent(new CustomEvent(emailFilterChangedEventName, { detail: { mode: emailFilterMode.value } }));
});
</script>
