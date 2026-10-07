<script setup>
import { Head } from '@statamic/cms/inertia';
import { Button, Header } from '@statamic/cms/ui';
import ProCard from '../components/ProCard.vue';

/*
 * A Pro screen opened in the free edition (a bookmark, a link from the docs):
 * what Pro adds, rather than a bare 404.
 */
defineProps({
    upgradeUrl: { type: String, required: true },
    severalSites: { type: Boolean, default: false },
    overviewUrl: { type: String, required: true },
});
</script>

<template>
    <Head :title="__('seo::cp.seo')" />

    <div class="max-w-page mx-auto space-y-6">
        <Header :title="__('seo::cp.seo')" icon="search-magnifying-glass">
            <Button :href="overviewUrl" :text="__('seo::cp.search_console.overview')" variant="ghost" />
        </Header>

        <ProCard :url="upgradeUrl" :several-sites="severalSites" />
    </div>
</template>
