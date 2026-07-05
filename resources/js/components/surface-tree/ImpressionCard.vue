<template>
    <article aria-label="Impression card">
        <h2>{{ displayValue(label, 'Untitled impression') }}</h2>

        <dl>
            <div v-for="field in visibleFields" :key="field.label">
                <dt>{{ field.label }}</dt>
                <dd>{{ field.value }}</dd>
            </div>
        </dl>

        <p v-if="canonicalHref">
            <a :href="canonicalHref">Open canonical impression</a>
        </p>
    </article>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { SurfaceMainContentState } from './types';

const props = defineProps<{
    state: SurfaceMainContentState;
}>();

const payload = computed(() => props.state.payload ?? {});
const meta = computed(() => {
    const value = payload.value.meta;

    return value && typeof value === 'object' && !Array.isArray(value)
        ? value as Record<string, unknown>
        : {};
});

const impressionId = computed(() => props.state.impression_id ?? valueFromPayload(['impression_id', 'id']));
const domain = computed(() => valueFromPayload(['domain']));
const label = computed(() => valueFromPayload(['label', 'title', 'name']));
const kind = computed(() => valueFromPayload(['kind', 'memory_kind', 'memoryKind']));
const status = computed(() => valueFromPayload(['status', 'process_status', 'processStatus']));
const observedTime = computed(() => valueFromPayload(['observed_at', 'observed_time', 'observedTime']));
const sourceReference = computed(() => valueFromPayload(['source_path', 'sourcePath', 'source_ref', 'sourceRef']));
const canonicalHref = computed(() => asString(payload.value.href));

const visibleFields = computed(() => [
    { label: 'impression id', value: asString(impressionId.value) },
    { label: 'domain', value: asString(domain.value) },
    { label: 'kind', value: asString(kind.value) },
    { label: 'status', value: asString(status.value) },
    { label: 'observed time', value: asString(observedTime.value) },
    { label: 'source', value: asString(sourceReference.value) },
].filter((field) => field.value));

function valueFromPayload(keys: string[]): unknown {
    for (const key of keys) {
        if (payload.value[key] !== undefined && payload.value[key] !== null) {
            return payload.value[key];
        }

        if (meta.value[key] !== undefined && meta.value[key] !== null) {
            return meta.value[key];
        }
    }

    return null;
}

function asString(value: unknown): string | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return null;
}

function displayValue(value: unknown, fallback: string): string {
    return asString(value) ?? fallback;
}
</script>
