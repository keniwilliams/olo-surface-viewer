<template>
    <article class="surface-tree__dreamstate-card" aria-label="Dreamstate impression card">
        <header class="surface-tree__dreamstate-header" aria-label="About this impression">
            <div class="surface-tree__dreamstate-heading">
                <span :class="['surface-tree__dreamstate-kind', `surface-tree__dreamstate-kind--${displayKind.slug}`]">
                    {{ displayKind.label }}
                </span>
                <h2 class="surface-tree__dreamstate-title">{{ title }}</h2>
            </div>
            <p v-if="formattedObservedAt" class="surface-tree__dreamstate-observed">
                Observed {{ formattedObservedAt }}
            </p>
        </header>

        <p class="surface-tree__dreamstate-summary">
            {{ summary ?? 'No summary has been captured for this impression yet.' }}
        </p>

        <section class="surface-tree__dreamstate-evolution" aria-label="Evolution state">
            <h3 class="surface-tree__dreamstate-section-title">Evolution</h3>

            <p v-if="evolutionView && evolutionView.steps.length > 0" class="surface-tree__dreamstate-evolution-path">
                <template v-for="(step, index) in evolutionView.steps" :key="step">
                    <span v-if="index > 0" class="surface-tree__dreamstate-evolution-arrow" aria-hidden="true">→</span>
                    <span
                        :class="[
                            'surface-tree__dreamstate-evolution-step',
                            index === evolutionView.steps.length - 1 ? 'surface-tree__dreamstate-evolution-step--current' : '',
                        ]"
                    >{{ step }}</span>
                </template>
            </p>

            <p v-else-if="evolutionView" class="surface-tree__corpus-muted">
                Not evolved yet. This impression was observed but has not become a Dreamstate candidate.
            </p>

            <p v-else class="surface-tree__corpus-muted">No Dreamstate evolution recorded yet.</p>
        </section>

        <div class="surface-tree__dreamstate-actions" role="group" aria-label="Impression actions">
            <button type="button" class="surface-tree__dreamstate-action" :aria-expanded="showContents" @click="toggleContents">
                {{ showContents ? 'Hide contents' : 'Open contents' }}
            </button>
            <button type="button" class="surface-tree__dreamstate-action" :aria-expanded="showConnections" @click="showConnections = !showConnections">
                {{ showConnections ? 'Hide connections' : 'Show connections' }}
            </button>
            <button type="button" class="surface-tree__dreamstate-action" :aria-expanded="showTechnical" @click="showTechnical = !showTechnical">
                Technical details
            </button>
        </div>

        <section v-if="showContents" class="surface-tree__dreamstate-section" aria-label="Contains">
            <h3 class="surface-tree__dreamstate-section-title">Contains</h3>
            <p v-if="isLoadingCorpus" class="surface-tree__corpus-muted">Opening contents...</p>
            <p v-else-if="corpusError" class="surface-tree__corpus-muted" role="alert">{{ corpusError }}</p>
            <p v-else-if="!compiledCorpus" class="surface-tree__corpus-muted">This impression has no readable contents.</p>
            <!-- Read-only rendered markdown; the corpus is never editable here. -->
            <div v-else class="surface-tree__corpus-body" v-html="compiledCorpus"></div>
        </section>

        <section v-if="showConnections" class="surface-tree__dreamstate-section" aria-label="Linked impressions">
            <h3 class="surface-tree__dreamstate-section-title">Connections</h3>
            <ul v-if="linkedImpressions.length > 0" class="surface-tree__dreamstate-links">
                <li v-for="link in linkedImpressions" :key="link.id ?? link.label">
                    <a v-if="link.id" :href="`/impressions/${encodeURIComponent(link.id)}`">{{ link.label }}</a>
                    <template v-else>{{ link.label }}</template>
                </li>
            </ul>
            <p v-else class="surface-tree__corpus-muted">No linked impressions recorded for this impression yet.</p>
        </section>

        <section v-if="showTechnical" class="surface-tree__dreamstate-section" aria-label="Technical details">
            <h3 class="surface-tree__dreamstate-section-title">Technical details</h3>
            <dl v-if="technicalFields.length > 0" class="surface-tree__details">
                <div v-for="field in technicalFields" :key="field.label" class="surface-tree__detail-row">
                    <dt class="surface-tree__detail-label">{{ field.label }}</dt>
                    <dd class="surface-tree__detail-value">{{ field.value }}</dd>
                </div>
            </dl>
            <p v-else class="surface-tree__corpus-muted">No technical details available.</p>
        </section>
    </article>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { marked } from 'marked';
import { formatDateTime } from '../../support/dateFormatter';
import { displayKindFor, evolutionViewFrom, linkedImpressionsFrom } from './dreamstateDisplay';
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

const impressionId = computed(() => asString(props.state.impression_id ?? valueFromPayload(['impression_id', 'id'])));
const title = computed(() => asString(valueFromPayload(['label', 'title', 'name'])) ?? 'Unnamed impression');
const summary = computed(() => asString(valueFromPayload(['summary'])));
const observedAt = computed(() => asString(valueFromPayload(['observed_at', 'observed_time', 'observedTime'])));
const formattedObservedAt = computed(() => observedAt.value ? formatDateTime(observedAt.value) : null);

// The display kind comes solely from the memory_kind the backend resolved
// via impressions_dreamstate_feed; unresolved impressions stay Unknown.
const displayKind = computed(() => displayKindFor(asString(valueFromPayload(['memory_kind']))));

// The evolution path was resolved server-side from the subconscious
// lineage tables; the card only renders the plain-label steps.
const evolutionView = computed(() => evolutionViewFrom(meta.value));

// Reports whether the backend managed to resolve this impression's
// provenance against the Impressions feed, for the technical section only.
const provenanceStatus = computed(() => {
    if (meta.value.provenance_resolved === true) {
        return 'resolved via impressions_dreamstate_feed';
    }

    if (meta.value.provenance_resolved === false) {
        const error = asString(meta.value.provenance_resolution_error);

        return error ? `unresolved (${error})` : 'unresolved';
    }

    return null;
});
const linkedImpressions = computed(() => linkedImpressionsFrom(meta.value));

const showContents = ref(false);
const showConnections = ref(false);
const showTechnical = ref(false);

const rawCorpus = ref<string | null>(null);
const hasFetchedCorpus = ref(false);
const isLoadingCorpus = ref(false);
const corpusError = ref<string | null>(null);

const compiledCorpus = computed(() => {
    if (!rawCorpus.value) {
        return null;
    }

    return marked.parse(rawCorpus.value, { async: false });
});

// IDs and source refs stay behind this collapsed section by design: the
// front-door experience is the meaning card, not the pipework.
const technicalFields = computed(() => [
    { label: 'impression id', value: impressionId.value },
    { label: 'node key', value: asString(payload.value.key) },
    { label: 'domain', value: asString(valueFromPayload(['domain'])) },
    { label: 'memory kind', value: asString(valueFromPayload(['memory_kind'])) },
    { label: 'memory source ref', value: asString(valueFromPayload(['memory_source_ref'])) },
    { label: 'contract version', value: asString(valueFromPayload(['contract_version'])) },
    { label: 'provenance', value: provenanceStatus.value },
    { label: 'kind', value: asString(valueFromPayload(['kind'])) },
    { label: 'status', value: asString(valueFromPayload(['status', 'process_status'])) },
    { label: 'schema', value: asString(valueFromPayload(['schema'])) },
    { label: 'source ref', value: asString(valueFromPayload(['source_ref'])) },
    { label: 'source path', value: asString(valueFromPayload(['source_path'])) },
    { label: 'evolution stage', value: asString(valueFromPayload(['evolution_stage'])) },
    { label: 'run id', value: asString(valueFromPayload(['run_id', 'runId'])) },
    { label: 'candidate id', value: asString(valueFromPayload(['candidate_id'])) },
    { label: 'candidate status', value: asString(valueFromPayload(['candidate_status'])) },
    { label: 'packet id', value: asString(valueFromPayload(['packet_id', 'packetId'])) },
    { label: 'sensemaker request id', value: asString(valueFromPayload(['sensemaker_request_id'])) },
    { label: 'sensemaker status', value: asString(valueFromPayload(['sensemaker_status'])) },
    { label: 'observed at', value: observedAt.value },
].filter((field) => field.value));

// A new impression resets everything the previous one loaded or expanded.
watch(
    () => impressionId.value,
    () => {
        rawCorpus.value = null;
        corpusError.value = null;
        hasFetchedCorpus.value = false;
        showContents.value = false;
        showConnections.value = false;
        showTechnical.value = false;
    },
);

function toggleContents() {
    showContents.value = !showContents.value;

    if (showContents.value && !hasFetchedCorpus.value && impressionId.value) {
        fetchCorpus(impressionId.value);
    }
}

async function fetchCorpus(id: string) {
    hasFetchedCorpus.value = true;
    isLoadingCorpus.value = true;
    corpusError.value = null;

    try {
        const response = await fetch(`/surface-tree/impressions/${encodeURIComponent(id)}/corpus`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            if (impressionId.value === id) {
                corpusError.value = `Request failed: ${response.status}`;
            }

            return;
        }

        const responsePayload = (await response.json()) as {
            data?: {
                raw_corpus?: string | null;
            };
        };

        if (impressionId.value !== id) {
            return;
        }

        rawCorpus.value = asString(responsePayload.data?.raw_corpus);
    } catch (error) {
        if (impressionId.value !== id) {
            return;
        }

        corpusError.value = error instanceof Error
            ? error.message
            : 'Contents could not be loaded.';
    } finally {
        if (impressionId.value === id) {
            isLoadingCorpus.value = false;
        }
    }
}

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
</script>
