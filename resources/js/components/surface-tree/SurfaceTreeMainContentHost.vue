<template>
    <section aria-label="Surface tree main content">
        <ImpressionCard v-if="state.mode === 'impression_card'" :state="state" />
        <EmailSenderCard v-else-if="state.mode === 'email_sender_card'" :state="state" :email-filter-mode="emailFilterMode" />
        <EmailRecordCard v-else-if="state.mode === 'email_record_card'" :state="state" />

        <p v-else-if="state.selectedNodeKey && emailFilterMode !== 'all'">
            The selected email is hidden by the current email filter.
        </p>

        <p v-else-if="state.selectedNodeKey">
            Select an impression to open a card.
        </p>

        <p v-else>
            Select a surface tree node.
        </p>
    </section>
</template>

<script setup lang="ts">
import EmailRecordCard from './EmailRecordCard.vue';
import EmailSenderCard from './EmailSenderCard.vue';
import ImpressionCard from './ImpressionCard.vue';
import type { EmailFilterMode, SurfaceMainContentState } from './types';

defineProps<{
    state: SurfaceMainContentState;
    emailFilterMode: EmailFilterMode;
}>();
</script>

