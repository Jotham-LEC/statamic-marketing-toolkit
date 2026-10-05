<script setup>
import { Button, ConfirmationModal } from '@statamic/cms/ui';

defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
});

const emit = defineEmits(['add', 'skip', 'cancel']);

// The modal also reports "cancel" as it closes after a confirm; answer once.
let answered = false;

function answer(choice) {
    if (answered) return;

    answered = true;
    emit(choice);
}
</script>

<template>
    <ConfirmationModal
        :open="true"
        title="This page’s address changes"
        button-text="Add redirect"
        cancel-text="Don’t add"
        @confirm="answer('add')"
        @cancel="answer('skip')"
    >
        <p class="mb-3">Saving moves the page:</p>
        <p class="mb-1 font-mono text-sm break-all">{{ from }}</p>
        <p class="mb-3 font-mono text-sm break-all">→ {{ to }}</p>
        <p class="mb-4">Add a 301 redirect from the old address, so links to it keep working?</p>
        <Button size="sm" variant="ghost" text="Don’t save yet" @click="answer('cancel')" />
    </ConfirmationModal>
</template>
