<script setup>
import { Head, Link } from '@statamic/cms/inertia';
import { Badge, Button, Header, Listing } from '@statamic/cms/ui';
import { getCurrentInstance, ref } from 'vue';
import When from '../components/When.vue';

const props = defineProps({
    listingUrl: { type: String, required: true },
    actionUrl: { type: String, required: true },
    createUrl: { type: String, required: true },
    // CSV in and out is Pro: null in the free edition, which links to it instead.
    exportUrl: { type: String, default: null },
    importUrl: { type: String, default: null },
    upgradeUrl: { type: String, default: null },
});

const { $axios: axios, $toast: toast } = getCurrentInstance().appContext.config.globalProperties;
const file = ref(null);
const importing = ref(false);
const listingKey = ref(0);

async function importCsv(event) {
    const chosen = event.target.files[0];
    event.target.value = '';
    if (!chosen) return;

    importing.value = true;

    try {
        const form = new FormData();
        form.append('file', chosen);
        const { data } = await axios.post(props.importUrl, form);
        const summary = __('seo::cp.redirects.imported', { created: data.created, updated: data.updated });

        data.errors.length
            ? toast.error(__('seo::cp.redirects.skipped', { summary, count: data.errors.length, errors: data.errors.join(' ') }), { duration: 15000 })
            : toast.success(summary);
        listingKey.value++;
    } catch (error) {
        toast.error(error.response?.data?.message ?? __('seo::cp.redirects.import_failed'));
    } finally {
        importing.value = false;
    }
}
</script>

<template>
    <Head :title="__('seo::cp.redirects.title')" />

    <Header :title="__('seo::cp.redirects.title')" icon="moved">
        <template v-if="importUrl && exportUrl">
            <input ref="file" type="file" accept=".csv,text/csv" class="hidden" @change="importCsv" />
            <Button :text="__('seo::cp.redirects.import')" :loading="importing" @click="file.click()" />
            <Button :text="__('seo::cp.redirects.export')" :href="exportUrl" />
        </template>
        <Button
            v-else-if="upgradeUrl"
            :text="__('seo::cp.redirects.import') + ' / ' + __('seo::cp.redirects.export')"
            icon="padlock-locked"
            :href="upgradeUrl"
            target="_blank"
            v-tooltip="__('seo::cp.pro.csv')"
        />
        <Button :text="__('seo::cp.redirects.create')" variant="primary" :href="createUrl" />
    </Header>

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('seo::cp.redirects.intro') }}</p>

    <Listing
        :key="listingKey"
        :url="listingUrl"
        :action-url="actionUrl"
        :action-context="{ type: 'redirects' }"
        sort-column="source"
        sort-direction="asc"
        preferences-prefix="seo.redirects"
        :allow-presets="false"
    >
        <template #cell-source="{ row }">
            <Link :href="row.edit_url" class="font-mono text-sm">{{ row.source }}</Link>
            <Badge v-if="row.automatic" size="sm" class="ms-2" :text="__('seo::cp.redirects.automatic')" />
        </template>
        <template #cell-target="{ row }">
            <span class="font-mono text-sm">{{ row.target ?? '—' }}</span>
        </template>
        <template #cell-active="{ row }">
            <span :class="row.active ? 'text-(--theme-color-success)' : 'text-gray-500'">{{ row.active ? __('seo::cp.redirects.yes') : __('seo::cp.redirects.no') }}</span>
        </template>
        <template #cell-last_hit_at="{ row }">
            <When :value="row.last_hit_at" />
        </template>
    </Listing>
</template>
