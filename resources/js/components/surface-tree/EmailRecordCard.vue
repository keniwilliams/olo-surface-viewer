<template>
    <article class="surface-tree__email_card" aria-label="Email record card">
        <h2 class="surface-tree__card-title">{{ displayValue(label, 'Email') }}</h2>

        <dl class="surface-tree__details">
            <div v-for="field in visibleFields" :key="field.label" class="surface-tree__detail-row">
                <dt class="surface-tree__detail-label">{{ field.label }}</dt>
                <dd class="surface-tree__detail-value">{{ field.value }}</dd>
            </div>
        </dl>

        <section class="surface-tree__email-sections" aria-label="Email sensemaking">
            <section v-if="bodyPreview" class="surface-tree__email-section">
                <h3 class="surface-tree__corpus-title">preview</h3>
                <p class="surface-tree__email-body">{{ bodyPreview }}</p>
            </section>

            <section v-if="emailBody" class="surface-tree__email-section">
                <h3 class="surface-tree__corpus-title">email body</h3>
                <div class="surface-tree__email-body">
                    <p v-for="paragraph in emailBodyParagraphs" :key="paragraph">
                        {{ paragraph }}
                    </p>
                </div>
            </section>

            <section v-if="sensemadeText" class="surface-tree__email-section">
                <h3 class="surface-tree__corpus-title">sensemade text</h3>
                <!-- Read-only rendered markdown; the sensemade text is never editable here. -->
                <div class="surface-tree__corpus-body" v-html="compiledSensemadeText"></div>
            </section>

            <section v-for="section in visibleSections" :key="section.label" class="surface-tree__email-section">
                <h3 class="surface-tree__corpus-title">{{ section.label }}</h3>
                <p class="surface-tree__email-body">{{ section.value }}</p>
            </section>

            <p v-if="!bodyPreview && !emailBody && !sensemadeText && visibleSections.length === 0" class="surface-tree__corpus-muted">
                No email summary available.
            </p>
        </section>
    </article>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { marked } from 'marked';
import { formatDateTime } from '../../support/dateFormatter';
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

const label = computed(() => valueFromPayload(['label', 'subject', 'title']));
const sender = computed(() => valueFromPayload(['sender', 'from_address', 'from_email']));
const receivedAt = computed(() => valueFromPayload(['received_at', 'observed_at']));
const bodyPreview = computed(() => asString(valueFromPayload(['body_preview', 'bodyPreview', 'preview', 'snippet'])));
const emailBody = computed(() => asString(valueFromPayload(['email_body', 'emailbody', 'normalised_body', 'normalized_body', 'body', 'body_text', 'text_body', 'plain_text', 'message_body', 'content'])));
const formattedReceivedAt = computed(() => receivedAt.value ? formatDateTime(receivedAt.value) : null);
const emailBodyParagraphs = computed(() => splitParagraphs(emailBody.value));
const sensemadeText = computed(() => asString(valueFromPayload(['sensemade_text', 'sensemadeText'])));
const compiledSensemadeText = computed(() => {
    if (!sensemadeText.value) {
        return null;
    }

    return marked.parse(sensemadeText.value, { async: false });
});

const visibleFields = computed(() => [
    { label: 'sender', value: asString(sender.value) },
    { label: 'received', value: formattedReceivedAt.value },
].filter((field) => field.value));

const visibleSections = computed(() => [
    { label: 'human summary', value: asString(valueFromPayload(['human_summary', 'humanSummary'])) },
    { label: 'why it matters', value: asString(valueFromPayload(['why_it_matters', 'whyItMatters'])) },
    { label: 'recommended next step', value: asString(valueFromPayload(['recommended_next_step', 'recommendedNextStep'])) },
].filter((section) => section.value));

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

function splitParagraphs(value: string | null): string[] {
    if (!value) {
        return [];
    }

    return value
        .split(/\r?\n+/)
        .map((paragraph) => paragraph.trim())
        .filter((paragraph) => paragraph.length > 0);
}
</script>
