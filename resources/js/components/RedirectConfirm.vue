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
        :title="__('seo::cp.confirm.title')"
        :button-text="__('seo::cp.confirm.add')"
        :cancel-text="__('seo::cp.confirm.dont_add')"
        @confirm="answer('add')"
        @cancel="answer('skip')"
    >
        <p class="mb-3">{{ __('seo::cp.confirm.moves') }}</p>
        <p class="mb-1 font-mono text-sm break-all">{{ from }}</p>
        <p class="mb-3 font-mono text-sm break-all">→ {{ to }}</p>
        <p class="mb-4">{{ __('seo::cp.confirm.question') }}</p>
        <Button size="sm" variant="ghost" :text="__('seo::cp.confirm.not_yet')" @click="answer('cancel')" />
    </ConfirmationModal>
</template>
