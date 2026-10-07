<script setup>
import { numberFormatter } from '@statamic/cms/api';
import { Head } from '@statamic/cms/inertia';
import { Alert, Button, Card, Description, Header, Heading, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import ProCard from '../components/ProCard.vue';
import Gauge from '../components/Gauge.vue';
import When from '../components/When.vue';

defineProps({
    siteName: { type: String, required: true },
    upgradeUrl: { type: String, required: true },
    global: { type: Object, required: true },
    // Pro's panels: null in the free edition, which shows what Pro adds instead.
    report: { type: Object, default: null },
    redirects: { type: Object, default: null },
    notFound: { type: Object, default: null },
    search: { type: Object, default: null },
    searchConsole: { type: Object, default: null },
    files: { type: Array, required: true },
    severalSites: { type: Boolean, default: false },
    tracking: { type: Object, required: true },
});
</script>

<template>
    <Head :title="__('seo::cp.seo')" />

    <div class="max-w-page mx-auto space-y-6">
        <Header :title="__('seo::cp.seo')" icon="search-magnifying-glass" />

        <Card v-if="tracking.overlap.length" class="flex flex-wrap items-center justify-between gap-3 border-amber-400 p-4">
            <div class="space-y-1">
                <Heading size="lg">{{ __('seo::cp.tracking.overlap_title') }}</Heading>
                <Description>{{ __('seo::cp.tracking.overlap', { tools: tracking.overlap.join(', ') }) }}</Description>
            </div>
            <Button v-if="tracking.url" :href="tracking.url" :text="__('seo::cp.tracking.edit')" />
        </Card>

        <Alert v-if="severalSites" variant="warning" icon="alert-warning-exclamation-mark" :text="__('seo::cp.pro.sites.free')" :live="false" />

        <div class="grid gap-6 md:grid-cols-2">
            <Card v-if="report" class="flex flex-col gap-3 p-4">
                <Heading size="lg">{{ __('seo::cp.overview.report.title') }}</Heading>
                <div v-if="report.latest" class="flex flex-wrap items-center gap-6">
                    <Gauge :value="report.latest.score" :label="__('seo::cp.gauge.label')" />
                    <div class="min-w-48 flex-1 space-y-2">
                        <Description>{{ __n('seo::cp.overview.report.pages', report.latest.pages, { count: report.latest.pages }) }} <When :value="report.latest.finished_at" /></Description>
                        <ul v-if="report.latest.checks.length" class="space-y-1 text-sm">
                            <li v-for="check in report.latest.checks" :key="check.label" class="flex justify-between gap-4 border-b border-dotted border-gray-300 pb-1 dark:border-gray-700">
                                <span class="truncate">{{ check.label }}</span>
                                <span class="shrink-0 tabular-nums font-semibold text-(--theme-color-danger)">{{ check.fail }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <Description v-else>{{ __('seo::cp.overview.report.none') }}</Description>
                <div class="mt-auto flex flex-wrap gap-2">
                    <Button v-if="report.latest" :href="report.latest.url" :text="__('seo::cp.overview.report.open_latest')" />
                    <Button :href="report.url" :text="__('seo::cp.overview.report.all')" />
                    <Button v-if="report.settings_url" :href="report.settings_url" :text="__('seo::cp.overview.report.settings')" variant="ghost" />
                </div>
            </Card>

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
                        <dd>{{ __('seo::cp.overview.brand.page_title') }}<template v-if="global.separator">{{ global.separator }}{{ siteName }}</template></dd>
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
                    {{ __('seo::cp.overview.search.summary', { clicks: numberFormatter.format(search.clicks), impressions: numberFormatter.format(search.impressions), from: search.from, to: search.to }) }}
                    {{ __('seo::cp.overview.search.updated') }} <When :value="search.fetched_at" />.
                </Description>
                <Table v-if="search.top.length" class="overflow-x-auto">
                    <TableColumns>
                        <TableColumn>{{ __('seo::cp.overview.search.page') }}</TableColumn>
                        <TableColumn>{{ __('seo::cp.overview.search.clicks') }}</TableColumn>
                        <TableColumn>{{ __('seo::cp.overview.search.impressions') }}</TableColumn>
                        <TableColumn>{{ __('seo::cp.overview.search.position') }}</TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="row in search.top" :key="row.path">
                            <TableCell><div class="max-w-48 truncate font-mono text-xs sm:max-w-md" :title="row.path">{{ row.path }}</div></TableCell>
                            <TableCell class="tabular-nums">{{ numberFormatter.format(row.clicks) }}</TableCell>
                            <TableCell class="tabular-nums">{{ numberFormatter.format(row.impressions) }}</TableCell>
                            <TableCell class="tabular-nums">{{ row.position }}</TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </template>
        </Card>

        <Card v-else-if="searchConsole" class="flex flex-wrap items-center justify-between gap-3 p-4">
            <div class="space-y-1">
                <Heading size="lg">{{ __('seo::cp.overview.search.title') }}</Heading>
                <Description>{{ __('seo::cp.overview.search.connect_body') }}</Description>
            </div>
            <Button :href="searchConsole.url" :text="__('seo::cp.overview.search.connect')" icon-append="arrow-right" />
        </Card>

        <ProCard v-if="!report" :url="upgradeUrl" :several-sites="severalSites" />

        <Card class="space-y-2 p-4">
            <Heading size="lg">{{ __('seo::cp.tracking.title') }}</Heading>
            <Alert v-for="message in tracking.invalid" :key="message" variant="warning" :text="message" :live="false" />
            <Description v-if="!tracking.tools.length">{{ __('seo::cp.tracking.none') }}</Description>
            <template v-else>
                <ul class="space-y-1 text-sm">
                    <li v-for="tool in tracking.tools" :key="tool.name">
                        {{ tool.name }}: <span class="font-mono text-xs">{{ tool.id }}</span>
                        <span v-if="tool.from_env" class="text-xs text-gray-500">({{ __('seo::cp.tracking.from_env') }})</span>
                    </li>
                </ul>
                <Description>{{ __('seo::cp.tracking.live_only') }} <template v-if="tracking.consent">{{ __('seo::cp.tracking.consent') }}</template></Description>
            </template>
            <Button v-if="tracking.url" size="sm" :href="tracking.url" :text="__('seo::cp.tracking.edit')" />
        </Card>

        <Card class="space-y-2 p-4">
            <Heading size="lg">{{ __('seo::cp.overview.files.title') }}</Heading>
            <ul class="space-y-1 text-sm">
                <li v-for="file in files" :key="file.label">
                    {{ file.label }}:
                    <a :href="file.url" target="_blank" rel="noopener" class="font-mono text-xs underline">{{ file.url }}</a>
                    <span v-if="file.public" class="ml-1 text-xs text-amber-700 dark:text-amber-400">{{ __('seo::cp.overview.files.public') }}</span>
                </li>
            </ul>
        </Card>
    </div>
</template>
