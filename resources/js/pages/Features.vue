<script setup>
import { Head } from '@statamic/cms/inertia';
import { PublishForm } from '@statamic/cms/ui';

defineProps({
    blueprint: { type: Object, required: true },
    values: { type: Object, required: true },
    meta: { type: Object, required: true },
    submitUrl: { type: String, required: true },
});
</script>

<template>
    <Head :title="__('marketing-toolkit::cp.features.title')" />

    <div class="max-w-page mx-auto">
        <PublishForm
            :title="__('marketing-toolkit::cp.features.title')"
            icon="settings"
            :blueprint="blueprint"
            :initial-values="values"
            :initial-meta="meta"
            :submit-url="submitUrl"
            submit-method="post"
        />
    </div>
</template>
