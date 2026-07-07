<template>
    <article class="surface-tree__card" aria-label="Email sender card">
        <h2 class="surface-tree__card-title">{{ displayValue(sender, 'Email sender') }}</h2>

        <dl class="surface-tree__details">
            <div v-for="field in visibleFields" :key="field.label" class="surface-tree__detail-row">
                <dt class="surface-tree__detail-label">{{ field.label }}</dt>
                <dd class="surface-tree__detail-value">{{ field.value }}</dd>
            </div>
        </dl>

        <section class="surface-tree__email-list" aria-label="Emails from sender">
            <p v-if="isLoadingMessages" class="surface-tree__corpus-muted">Loading emails...</p>
            <p v-else-if="messageError" class="surface-tree__corpus-muted" role="alert">{{ messageError }}</p>
            <p v-else-if="visibleMessages.length === 0" class="surface-tree__corpus-muted">{{ emptyMessage }}</p>

            <template v-else>
                <EmailRecordCard
                    v-for="message in visibleMessages"
                    :key="message.key"
                    :state="messageState(message)"
                />
            </template>
        </section>
    </article>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { formatDateTime } from '../../support/dateFormatter';
import { nodeMatchesEmailFilter } from './emailFilters';
import EmailRecordCard from './EmailRecordCard.vue';
import type { EmailFilterMode, SurfaceMainContentState, SurfaceTreeNode } from './types';

const props = defineProps<{
    state: SurfaceMainContentState;
    emailFilterMode: EmailFilterMode;
}>();

const payload = computed(() => props.state.payload ?? {});
const meta = computed(() => {
    const value = payload.value.meta;

    return value && typeof value === 'object' && !Array.isArray(value)
        ? value as Record<string, unknown>
        : {};
});

const selectedNodeKey = computed(() => props.state.selectedNodeKey ?? asString(payload.value.key));
const sender = computed(() => valueFromPayload(['sender', 'label']));
const messageCount = computed(() => valueFromPayload(['message_count', 'messageCount']));
const latestSubject = computed(() => valueFromPayload(['latest_subject', 'latestSubject']));
const latestReceivedAt = computed(() => valueFromPayload(['latest_received_at', 'latestReceivedAt']));
const formattedLatestReceivedAt = computed(() => latestReceivedAt.value ? formatDateTime(latestReceivedAt.value) : null);

const messages = ref<SurfaceTreeNode[]>([]);
const isLoadingMessages = ref(false);
const messageError = ref<string | null>(null);
const visibleMessages = computed(() => messages.value.filter((message) => nodeMatchesEmailFilter(message, props.emailFilterMode)));
const emptyMessage = computed(() => ({
    all: 'No emails available.',
    sensemade: 'No sensemade emails available.',
    non_sensemade: 'No non sensemade emails available.',
}[props.emailFilterMode]));

const visibleFields = computed(() => [
    { label: 'messages', value: asString(messageCount.value) },
    { label: 'latest subject', value: asString(latestSubject.value) },
    { label: 'latest received', value: formattedLatestReceivedAt.value },
].filter((field) => field.value));

watch(
    () => selectedNodeKey.value,
    (nodeKey) => {
        messages.value = [];
        messageError.value = null;

        if (nodeKey) {
            fetchMessages(nodeKey);
        }
    },
    { immediate: true },
);

async function fetchMessages(nodeKey: string) {
    isLoadingMessages.value = true;
    messageError.value = null;

    try {
        const response = await fetch(`/surface-tree/nodes/${encodeURIComponent(nodeKey)}/children?depth_window=3`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            if (selectedNodeKey.value === nodeKey) {
                messageError.value = `Request failed: ${response.status}`;
            }

            return;
        }

        const responsePayload = (await response.json()) as { data?: SurfaceTreeNode[] };

        if (selectedNodeKey.value !== nodeKey) {
            return;
        }

        messages.value = Array.isArray(responsePayload.data) ? responsePayload.data : [];
    } catch (error) {
        if (selectedNodeKey.value !== nodeKey) {
            return;
        }

        messageError.value = error instanceof Error
            ? error.message
            : 'Emails could not be loaded.';
    } finally {
        if (selectedNodeKey.value === nodeKey) {
            isLoadingMessages.value = false;
        }
    }
}

function messageState(message: SurfaceTreeNode): SurfaceMainContentState {
    return {
        mode: 'email_record_card',
        selectedNodeKey: message.key,
        payload: {
            key: message.key,
            label: message.label,
            type: message.type,
            domain: message.domain,
            impression_id: message.impression_id ?? null,
            relation: message.relation ?? null,
            depth: message.depth,
            has_children: message.has_children,
            is_terminal_depth: message.is_terminal_depth,
            href: message.href ?? null,
            meta: message.meta,
        },
    };
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

function displayValue(value: unknown, fallback: string): string {
    return asString(value) ?? fallback;
}
</script>
