<template>
    <article class="surface-tree__card" aria-label="Impression card">
        <h2 class="surface-tree__card-title">{{ displayValue(label, 'Untitled impression') }}</h2>

        <dl class="surface-tree__details">
            <div v-for="field in visibleFields" :key="field.label" class="surface-tree__detail-row">
                <dt class="surface-tree__detail-label">{{ field.label }}</dt>
                <dd class="surface-tree__detail-value">{{ field.value }}</dd>
            </div>
        </dl>

        <section v-if="impressionId" class="surface-tree__corpus" aria-label="Raw corpus">
            <h3 class="surface-tree__corpus-title">raw corpus</h3>

            <p v-if="isLoadingCorpus" class="surface-tree__corpus-muted">Loading corpus...</p>
            <p v-else-if="corpusError" class="surface-tree__corpus-muted" role="alert">{{ corpusError }}</p>
            <p v-else-if="!compiledMarkdown" class="surface-tree__corpus-muted">No corpus available.</p>
            <!-- Read-only rendered markdown; the corpus is never editable here. -->
            <div v-else class="surface-tree__corpus-body" v-html="compiledMarkdown"></div>
        </section>

        <p v-if="canonicalHref">
            <a :href="canonicalHref">Open canonical impression</a>
        </p>
    </article>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { marked } from 'marked';
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

const rawCorpus = ref<string | null>(null);
const isLoadingCorpus = ref(false);
const corpusError = ref<string | null>(null);

const compiledMarkdown = computed(() => {
    if (!rawCorpus.value) {
        return null;
    }

    return marked.parse(rawCorpus.value, { async: false });
});

// The card renders immediately from the node payload; the corpus arrives
// afterwards from its own endpoint so tree responses stay light.
watch(
    () => asString(impressionId.value),
    (id) => {
        rawCorpus.value = null;
        corpusError.value = null;

        if (id) {
            fetchCorpus(id);
        }
    },
    { immediate: true },
);

async function fetchCorpus(id: string) {
    isLoadingCorpus.value = true;

    try {
        const response = await fetch(`/surface-tree/impressions/${encodeURIComponent(id)}/corpus`, {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`Request failed: ${response.status}`);
        }

        const payload = (await response.json()) as { data?: { impression_id?: string; raw_corpus?: string | null } };

        if (asString(impressionId.value) !== id) {
            return;
        }

        rawCorpus.value = asString(payload.data?.raw_corpus);
    } catch (error) {
        if (asString(impressionId.value) !== id) {
            return;
        }

        corpusError.value = error instanceof Error ? error.message : 'Corpus could not be loaded.';
    } finally {
        if (asString(impressionId.value) === id) {
            isLoadingCorpus.value = false;
        }
    }
}

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
