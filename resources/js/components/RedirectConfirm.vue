<script setup>
import { Button, Modal } from '@statamic/cms/ui';

defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
});

// Closing it (Escape, a click outside) is "not yet": nothing is saved, nothing decided.
const emit = defineEmits(['add', 'skip', 'cancel']);
</script>

<template>
    <Modal :open="true" :title="__('marketing-toolkit::cp.confirm.title')" @dismissed="emit('cancel')">
        <p>{{ __('marketing-toolkit::cp.confirm.moves') }}</p>
        <p class="font-mono text-sm break-all">{{ from }}<br />→ {{ to }}</p>
        <p>{{ __('marketing-toolkit::cp.confirm.question') }}</p>

        <template #footer>
            <div class="flex flex-wrap items-center justify-end gap-3 pt-3 pb-1">
                <Button variant="ghost" :text="__('marketing-toolkit::cp.confirm.not_yet')" @click="emit('cancel')" />
                <Button :text="__('marketing-toolkit::cp.confirm.dont_add')" @click="emit('skip')" />
                <Button variant="primary" :text="__('marketing-toolkit::cp.confirm.add')" @click="emit('add')" />
            </div>
        </template>
    </Modal>
</template>
