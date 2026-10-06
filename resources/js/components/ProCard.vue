<script setup>
import { Badge, Button, Card, Description, Heading } from '@statamic/cms/ui';

/*
 * A Pro feature in the free edition: what it does, and where to get it.
 */
defineProps({
    title: { type: String, required: true },
    body: { type: String, required: true },
    url: { type: String, required: true },
});
</script>

<template>
    <Card class="flex flex-col gap-3 p-4">
        <div class="flex items-center gap-2">
            <Heading size="lg">{{ title }}</Heading>
            <Badge size="sm" color="purple" icon="padlock-locked" :text="__('seo::cp.pro.badge')" />
        </div>
        <Description>{{ body }}</Description>
        <div class="mt-auto"><Button :href="url" target="_blank" :text="__('seo::cp.pro.get')" icon-append="external-link" /></div>
    </Card>
</template>
