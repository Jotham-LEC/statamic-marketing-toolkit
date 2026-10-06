<script setup>
import { Head } from '@statamic/cms/inertia';
import { Button, Card, Description, Header, Heading } from '@statamic/cms/ui';
import Score from '../components/Score.vue';
import When from '../components/When.vue';

defineProps({
    siteName: { type: String, required: true },
    global: { type: Object, required: true },
    report: { type: Object, required: true },
    redirects: { type: Object, default: null },
    notFound: { type: Object, required: true },
    files: { type: Array, required: true },
});
</script>

<template>
    <Head title="SEO" />

    <div class="max-w-page mx-auto space-y-6">
        <Header title="SEO" icon="search-magnifying-glass" />

        <div class="grid gap-6 md:grid-cols-2">
            <Card class="flex flex-col gap-3 p-4">
                <Heading size="lg">Report</Heading>
                <div v-if="report.latest" class="flex items-center gap-3">
                    <Score :value="report.latest.score" />
                    <Description>{{ report.latest.pages }} pages, <When :value="report.latest.finished_at" /></Description>
                </div>
                <Description v-else>No report yet. A report renders every page and scores it out of 100.</Description>
                <div class="mt-auto flex flex-wrap gap-2">
                    <Button v-if="report.latest" :href="report.latest.url" text="Open the latest report" />
                    <Button :href="report.url" text="All reports" />
                    <Button v-if="report.settings_url" :href="report.settings_url" text="Report settings" variant="ghost" />
                </div>
            </Card>

            <Card class="flex flex-col gap-3 p-4">
                <Heading size="lg">404s</Heading>
                <Description v-if="!notFound.paths">No missing pages logged yet.</Description>
                <template v-else>
                    <Description>{{ notFound.paths }} missing {{ notFound.paths === 1 ? 'address' : 'addresses' }} logged. Most recent:</Description>
                    <ul class="space-y-1 text-sm">
                        <li v-for="row in notFound.recent" :key="row.path" class="flex justify-between gap-4">
                            <span class="truncate font-mono text-xs">{{ row.path }}</span>
                            <span class="shrink-0 tabular-nums text-gray-500">{{ row.hits }}×</span>
                        </li>
                    </ul>
                </template>
                <div class="mt-auto"><Button :href="notFound.url" text="All 404s" /></div>
            </Card>

            <Card v-if="redirects" class="flex flex-col gap-3 p-4">
                <Heading size="lg">Redirects</Heading>
                <Description>
                    {{ redirects.active }} active, {{ redirects.automatic }} of them added when a page moved.
                    A redirect only applies where the site would show a 404.
                </Description>
                <div class="mt-auto"><Button :href="redirects.url" text="Manage redirects" /></div>
            </Card>

            <Card class="flex flex-col gap-3 p-4">
                <Heading size="lg">Brand and defaults</Heading>
                <Description v-if="!global.exists">
                    The “SEO &amp; brand” global set is missing. Run <code>php please seo:install</code> to create it.
                </Description>
                <template v-else>
                    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                        <dt class="text-gray-500">Site name</dt>
                        <dd>{{ siteName }}</dd>
                        <dt class="text-gray-500">Titles</dt>
                        <dd>Page title{{ global.separator }}{{ siteName }}</dd>
                        <dt class="text-gray-500">Default description</dt>
                        <dd>{{ global.description ?? 'None: pages use their own text' }}</dd>
                    </dl>
                    <div class="mt-auto"><Button v-if="global.url" :href="global.url" text="Edit brand and defaults" /></div>
                </template>
            </Card>
        </div>

        <Card class="space-y-2 p-4">
            <Heading size="lg">What the site serves</Heading>
            <ul class="space-y-1 text-sm">
                <li v-for="file in files" :key="file.label">
                    {{ file.label }}:
                    <a :href="file.url" target="_blank" rel="noopener" class="font-mono text-xs underline">{{ file.url }}</a>
                </li>
            </ul>
        </Card>
    </div>
</template>
