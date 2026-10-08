<script setup>
import { Fieldtype } from '@statamic/cms';
import { Heading } from '@statamic/cms/ui';

/**
 * This draws a heading between the SEO tab's fields. It shows a rule above, and
 * the field's own label, translated by the server, because the field itself
 * leaves the label out (hide_display).
 */
const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose } = Fieldtype.use(emit, props);
defineExpose(expose);
</script>

<template>
    <div class="border-t border-gray-200 pt-6 dark:border-gray-700">
        <Heading size="lg">{{ meta?.display ?? config.display }}</Heading>
    </div>
</template>
