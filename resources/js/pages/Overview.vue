<script setup>
import { Head } from '@statamic/cms/inertia';
import { Button, Card, Description, Header, Heading } from '@statamic/cms/ui';
import ProCard from '../components/ProCard.vue';
import Score from '../components/Score.vue';
import When from '../components/When.vue';

defineProps({
    siteName: { type: String, required: true },
    edition: { type: String, required: true },
    upgradeUrl: { type: String, required: true },
    global: { type: Object, required: true },
    // Pro's panels: null in the free edition, which shows a Pro card in their place.
    report: { type: Object, default: null },
    redirects: { type: Object, default: null },
    notFound: { type: Object, default: null },
    search: { type: Object, default: null },
    searchConsole: { type: Object, default: null },
    files: { type: Array, required: true },
    severalSites: { type: Boolean, default: false },
});
</script>

<template>
    <Head :title="__('seo::cp.seo')" />

    <div class="max-w-page mx-auto space-y-6">
        <Header :title="__('seo::cp.seo')" icon="search-magnifying-glass" />

        <ProCard v-if="severalSites" :title="__('seo::cp.pro.sites.title')" :body="__('seo::cp.pro.sites.body')" :url="upgradeUrl" />

        <div class="grid gap-6 md:grid-cols-2">
            <Card v-if="report" class="flex flex-col gap-3 p-4">
                <Heading size="lg">{{ __('seo::cp.overview.report.title') }}</Heading>
                <div v-if="report.latest" class="flex items-center gap-3">
                    <Score :value="report.latest.score" />
                    <Description>{{ __n('seo::cp.overview.report.pages', report.latest.pages, { count: report.latest.pages }) }} <When :value="report.latest.finished_at" /></Description>
                </div>
                <Description v-else>{{ __('seo::cp.overview.report.none') }}</Description>
                <div class="mt-auto flex flex-wrap gap-2">
                    <Button v-if="report.latest" :href="report.latest.url" :text="__('seo::cp.overview.report.open_latest')" />
                    <Button :href="report.url" :text="__('seo::cp.overview.report.all')" />
                    <Button v-if="report.settings_url" :href="report.settings_url" :text="__('seo::cp.overview.report.settings')" variant="ghost" />
                </div>
            </Card>
            <ProCard v-else :title="__('seo::cp.pro.reports.title')" :body="__('seo::cp.pro.reports.body')" :url="upgradeUrl" />

            <Card v-if="notFound" class="flex flex-col gap-3 p-4">
                <Heading size="lg">{{ __('seo::cp.overview.not_found.title') }}</Heading>
                <Description v-if="!notFound.paths">{{ __('seo::cp.overview.not_found.none') }}</Description>
                <template v-else>
                    <Description>{{ __n('seo::cp.overview.not_found.count', notFound.paths, { count: notFound.paths }) }}</Description>
                    <ul class="space-y-1 text-sm">
                        <li v-for="row in notFound.recent" :key="row.path" class="flex justify-between gap-4">
                            <span class="truncate font-mono text-xs">{{ row.path }}</span>
                            <span class="shrink-0 tabular-nums text-gray-500">{{ row.hits }}×</span>
                        </li>
                    </ul>
                </template>
                <div class="mt-auto"><Button :href="notFound.url" :text="__('seo::cp.overview.not_found.all')" /></div>
            </Card>
            <ProCard v-else :title="__('seo::cp.pro.not_found.title')" :body="__('seo::cp.pro.not_found.body')" :url="upgradeUrl" />

            <Card v-if="redirects" class="flex flex-col gap-3 p-4">
                <Heading size="lg">{{ __('seo::cp.overview.redirects.title') }}</Heading>
                <Description>{{ __('seo::cp.overview.redirects.summary', { active: redirects.active, automatic: redirects.automatic }) }}</Description>
                <div class="mt-auto"><Button :href="redirects.url" :text="__('seo::cp.overview.redirects.manage')" /></div>
            </Card>

            <Card class="flex flex-col gap-3 p-4">
                <Heading size="lg">{{ __('seo::cp.overview.brand.title') }}</Heading>
                <Description v-if="!global.exists">{{ __('seo::cp.overview.brand.missing', { command: 'php please seo:install' }) }}</Description>
                <template v-else>
                    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                        <dt class="text-gray-500">{{ __('seo::cp.overview.brand.site_name') }}</dt>
                        <dd>{{ siteName }}</dd>
                        <dt class="text-gray-500">{{ __('seo::cp.overview.brand.titles') }}</dt>
                        <dd>{{ __('seo::cp.overview.brand.page_title') }}{{ global.separator }}{{ siteName }}</dd>
                        <dt class="text-gray-500">{{ __('seo::cp.overview.brand.default_description') }}</dt>
                        <dd>{{ global.description ?? __('seo::cp.overview.brand.no_description') }}</dd>
                    </dl>
                    <div class="mt-auto"><Button v-if="global.url" :href="global.url" :text="__('seo::cp.overview.brand.edit')" /></div>
                </template>
            </Card>
        </div>

        <Card v-if="search" class="space-y-3 p-4">
            <div class="flex items-center justify-between gap-3">
                <Heading size="lg">{{ __('seo::cp.overview.search.title') }}</Heading>
                <Button size="sm" variant="ghost" :href="searchConsole.url" :text="__('seo::cp.overview.search.manage')" />
            </div>
            <Description v-if="!search.fetched_at">{{ __('seo::cp.overview.search.not_imported') }}</Description>
            <template v-else>
                <Description>
                    {{ __('seo::cp.overview.search.summary', { clicks: search.clicks.toLocaleString(), impressions: search.impressions.toLocaleString(), from: search.from, to: search.to }) }}
                    {{ __('seo::cp.overview.search.updated') }} <When :value="search.fetched_at" />.
                </Description>
                <table v-if="search.top.length" class="w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr>
                            <th class="py-1 font-medium">{{ __('seo::cp.overview.search.page') }}</th>
                            <th class="py-1 text-right font-medium">{{ __('seo::cp.overview.search.clicks') }}</th>
                            <th class="py-1 text-right font-medium">{{ __('seo::cp.overview.search.impressions') }}</th>
                            <th class="py-1 text-right font-medium">{{ __('seo::cp.overview.search.position') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in search.top" :key="row.path" class="border-t border-gray-200 dark:border-gray-700">
                            <td class="truncate py-1 font-mono text-xs">{{ row.path }}</td>
                            <td class="py-1 text-right tabular-nums">{{ row.clicks }}</td>
                            <td class="py-1 text-right tabular-nums">{{ row.impressions }}</td>
                            <td class="py-1 text-right tabular-nums">{{ row.position }}</td>
                        </tr>
                    </tbody>
                </table>
            </template>
        </Card>

        <Card v-else-if="searchConsole" class="flex flex-wrap items-center justify-between gap-3 p-4">
            <div class="space-y-1">
                <Heading size="lg">{{ __('seo::cp.overview.search.title') }}</Heading>
                <Description>{{ __('seo::cp.overview.search.connect_body') }}</Description>
            </div>
            <Button :href="searchConsole.url" :text="__('seo::cp.overview.search.connect')" icon-append="arrow-right" />
        </Card>

        <ProCard v-else :title="__('seo::cp.pro.search_console.title')" :body="__('seo::cp.pro.search_console.body')" :url="upgradeUrl" />

        <Card class="space-y-2 p-4">
            <Heading size="lg">{{ __('seo::cp.overview.files.title') }}</Heading>
            <ul class="space-y-1 text-sm">
                <li v-for="file in files" :key="file.label">
                    {{ file.label }}:
                    <a :href="file.url" target="_blank" rel="noopener" class="font-mono text-xs underline">{{ file.url }}</a>
                </li>
            </ul>
        </Card>
    </div>
</template>
