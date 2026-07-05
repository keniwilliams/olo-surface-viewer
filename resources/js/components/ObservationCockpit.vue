<template>
    <section class="olo-cockpit" aria-label="OLO observation cockpit">
        <div class="grid grid-cols-[repeat(auto-fit,minmax(11rem,1fr))] gap-4 py-1" aria-label="Organ panel controls">
            <button
                v-for="organ in orderedOrgans"
                :key="organ.key"
                class="olo-organ-button p-3 wy-3"
                :class="{ 'olo-organ-button--hidden': !organ.visible }"
                type="button"
                draggable="true"
                :aria-pressed="organ.visible"
                @click="toggleOrgan(organ.key)"
                @dragstart="startDrag(organ.key)"
                @dragover.prevent
                @drop="dropOn(organ.key)"
            >
                <span class="olo-organ-button__drag block" aria-hidden="true">::</span>
                <span class="olo-organ-button__label block">{{ organ.label }}</span>
                <span class="olo-organ-button__state block">{{ organ.visible ? 'showing' : 'hidden' }}</span>
            </button>
        </div>

        <div v-if="loadError" class="olo-cockpit__notice" role="alert">
            {{ loadError }}
        </div>

        <div class="olo-cockpit__grid">
            <article
                v-for="organ in visibleOrgans"
                :key="organ.key"
                class="olo-organ-panel"
            >
                <header class="olo-organ-panel__header">
                    <div>
                        <h2>{{ organ.label }}</h2>
                        <p>{{ organ.key }}</p>
                    </div>
                    <span class="olo-status-pill" :data-status="organ.read_status">
                        {{ organ.read_status }}
                    </span>
                </header>

                <dl class="olo-organ-panel__details">
                    <div>
                        <dt>last successful read</dt>
                        <dd>{{ formatValue(organ.last_successful_read_at) }}</dd>
                    </div>
                    <div>
                        <dt>last observed activity</dt>
                        <dd>{{ formatValue(organ.last_observed_activity_at) }}</dd>
                    </div>
                    <div>
                        <dt>staleness</dt>
                        <dd>{{ organ.staleness_state }}</dd>
                    </div>
                    <div>
                        <dt>source</dt>
                        <dd>{{ organ.source }}</dd>
                    </div>
                    <div>
                        <dt>visible</dt>
                        <dd>{{ organ.visible ? 'true' : 'false' }}</dd>
                    </div>
                    <div>
                        <dt>sort order</dt>
                        <dd>{{ organ.sort_order }}</dd>
                    </div>
                </dl>

                <p class="olo-organ-panel__message">{{ organ.latest_message }}</p>

                <p v-if="organ.latest_error" class="olo-organ-panel__error">
                    {{ organ.latest_error }}
                </p>
            </article>
        </div>

        <section class="olo-feed" aria-label="Recent activity">
            <header class="olo-feed__header">
                <h2>Recent activity</h2>
                <button type="button" class="olo-refresh-button" @click="refresh" :disabled="isLoading">
                    {{ isLoading ? 'refreshing' : 'refresh' }}
                </button>
            </header>

            <ol class="olo-feed__list">
                <li
                    v-for="activity in visibleActivities"
                    :key="activityKey(activity)"
                    class="olo-feed-item"
                >
                    <div class="olo-feed-item__top">
                        <span>{{ activity.source_organ_label }}</span>
                        <span class="olo-status-pill" :data-status="activity.status">
                            {{ activity.status }}
                        </span>
                    </div>
                    <p class="olo-feed-item__message">{{ activity.message }}</p>
                    <dl class="olo-feed-item__details">
                        <div>
                            <dt>type</dt>
                            <dd>{{ activity.activity_type }}</dd>
                        </div>
                        <div>
                            <dt>time</dt>
                            <dd>{{ formatValue(activity.activity_timestamp) }}</dd>
                        </div>
                        <div>
                            <dt>source</dt>
                            <dd>{{ activity.source_reference }}</dd>
                        </div>
                        <div>
                            <dt>organ</dt>
                            <dd>{{ activity.source_organ_key }}</dd>
                        </div>
                    </dl>
                    <p v-if="activity.error" class="olo-feed-item__error">{{ activity.error }}</p>
                </li>
            </ol>
        </section>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    organsUrl: {
        type: String,
        required: true,
    },
    activityUrl: {
        type: String,
        required: true,
    },
});

const storageKey = 'olo.observationCockpit.organState.v1';
const organs = ref([]);
const activities = ref([]);
const draggedKey = ref(null);
const isLoading = ref(false);
const loadError = ref(null);

const orderedOrgans = computed(() => {
    return [...organs.value].sort((a, b) => a.sort_order - b.sort_order);
});

const visibleOrgans = computed(() => orderedOrgans.value.filter((organ) => organ.visible));
const visibleOrganKeys = computed(() => {
    return new Set(visibleOrgans.value.map((organ) => organ.key));
});
const visibleActivities = computed(() => {
    return activities.value.filter((activity) => visibleOrganKeys.value.has(activity.source_organ_key));
});

onMounted(() => {
    refresh();
});

async function refresh() {
    isLoading.value = true;
    loadError.value = null;

    try {
        const [organPayload, activityPayload] = await Promise.all([
            fetchJson(props.organsUrl),
            fetchJson(props.activityUrl),
        ]);

        organs.value = applyClientState(organPayload.data ?? []);
        activities.value = activityPayload.data ?? [];
    } catch (error) {
        loadError.value = error instanceof Error ? error.message : 'Observation cockpit data could not be loaded.';
    } finally {
        isLoading.value = false;
    }
}

async function fetchJson(url) {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
    }

    return response.json();
}

function applyClientState(apiOrgans) {
    const persisted = loadClientState();

    return apiOrgans
        .map((organ, index) => ({
            ...organ,
            visible: persisted[organ.key]?.visible ?? true,
            sort_order: persisted[organ.key]?.sort_order ?? index,
        }))
        .sort((a, b) => a.sort_order - b.sort_order)
        .map((organ, index) => ({
            ...organ,
            sort_order: index,
        }));
}

function loadClientState() {
    try {
        return JSON.parse(localStorage.getItem(storageKey) ?? '{}');
    } catch {
        return {};
    }
}

function persistClientState() {
    const state = Object.fromEntries(
        orderedOrgans.value.map((organ, index) => [
            organ.key,
            {
                visible: organ.visible,
                sort_order: index,
            },
        ]),
    );

    localStorage.setItem(storageKey, JSON.stringify(state));
}

function toggleOrgan(key) {
    organs.value = orderedOrgans.value.map((organ) => ({
        ...organ,
        visible: organ.key === key ? !organ.visible : organ.visible,
    }));

    persistClientState();
}

function startDrag(key) {
    draggedKey.value = key;
}

function dropOn(targetKey) {
    if (!draggedKey.value || draggedKey.value === targetKey) {
        draggedKey.value = null;
        return;
    }

    const next = orderedOrgans.value;
    const from = next.findIndex((organ) => organ.key === draggedKey.value);
    const to = next.findIndex((organ) => organ.key === targetKey);

    if (from === -1 || to === -1) {
        draggedKey.value = null;
        return;
    }

    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);

    organs.value = next.map((organ, index) => ({
        ...organ,
        sort_order: index,
    }));
    draggedKey.value = null;
    persistClientState();
}

function formatValue(value) {
    return value || 'unknown';
}

function activityKey(activity) {
    return [
        activity.source_organ_key,
        activity.activity_type,
        activity.activity_timestamp ?? 'untimed',
        activity.source_reference,
    ].join(':');
}
</script>
