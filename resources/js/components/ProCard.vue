<script setup>
import { Badge, Button, Card, Heading } from '@statamic/cms/ui';
import { computed } from 'vue';

/*
 * The free edition's overview: what Pro adds, and where to get it.
 */
const props = defineProps({
    url: { type: String, required: true },
    severalSites: { type: Boolean, default: false },
});

const features = computed(() => [...(props.severalSites ? ['sites'] : []), 'reports', 'not_found', 'search_console']);
</script>

<template>
    <Card class="space-y-3 p-4">
        <div class="flex items-center gap-2">
            <Heading size="lg">{{ __('marketing-toolkit::cp.pro.title') }}</Heading>
            <Badge size="sm" color="purple" icon="padlock-locked" :text="__('marketing-toolkit::cp.pro.badge')" />
        </div>
        <ul class="space-y-2 text-sm">
            <li v-for="feature in features" :key="feature">
                <strong>{{ __(`marketing-toolkit::cp.pro.${feature}.title`) }}:</strong>
                {{ __(`marketing-toolkit::cp.pro.${feature}.body`) }}
            </li>
        </ul>
        <Button :href="url" target="_blank" :text="__('marketing-toolkit::cp.pro.get')" icon-append="external-link" />
    </Card>
</template>
