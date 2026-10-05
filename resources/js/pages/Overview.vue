<script setup>
import { Head } from '@statamic/cms/inertia';
import { Card, Description, Header, Heading } from '@statamic/cms/ui';

defineProps({
    siteName: { type: String, required: true },
    global: { type: Object, required: true },
    files: { type: Array, required: true },
});
</script>

<template>
    <Head title="SEO" />

    <div class="max-w-page mx-auto space-y-6">
        <Header title="SEO" icon="search-magnifying-glass" />

        <Card class="space-y-2 p-4">
            <Heading size="lg">Brand and defaults</Heading>
            <Description v-if="!global.exists">
                The “SEO &amp; brand” global set is missing. Run <code>php please seo:install</code> to create it.
            </Description>
            <Description v-else>
                Site name ({{ siteName }}), default description and image, publisher, verification codes, robots.txt and share-card colours.
                <a v-if="global.url" :href="global.url" class="text-blue-600 underline dark:text-blue-400">Edit them</a>
            </Description>
        </Card>

        <Card class="space-y-2 p-4">
            <Heading size="lg">What the site serves</Heading>
            <ul class="space-y-1 text-sm">
                <li v-for="file in files" :key="file.label">
                    {{ file.label }}:
                    <a :href="file.url" target="_blank" rel="noopener" class="font-mono text-xs text-blue-600 underline dark:text-blue-400">{{ file.url }}</a>
                </li>
            </ul>
        </Card>
    </div>
</template>
