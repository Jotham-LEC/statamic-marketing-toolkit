<script setup>
import { Head, Link } from '@statamic/cms/inertia';
import { Badge, Button, Header, Listing } from '@statamic/cms/ui';
import { getCurrentInstance, ref } from 'vue';
import When from '../components/When.vue';

const props = defineProps({
    listingUrl: { type: String, required: true },
    actionUrl: { type: String, required: true },
    createUrl: { type: String, required: true },
    exportUrl: { type: String, required: true },
    importUrl: { type: String, required: true },
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
        const summary = `${data.created} added, ${data.updated} updated`;

        data.errors.length ? toast.error(`${summary}; ${data.errors.length} skipped. ${data.errors.join(' ')}`, { duration: 15000 }) : toast.success(summary);
        listingKey.value++;
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'The file could not be imported.');
    } finally {
        importing.value = false;
    }
}
</script>

<template>
    <Head title="Redirects" />

    <Header title="Redirects" icon="moved">
        <input ref="file" type="file" accept=".csv,text/csv" class="hidden" @change="importCsv" />
        <Button text="Import CSV" :loading="importing" @click="file.click()" />
        <Button text="Export CSV" :href="exportUrl" />
        <Button text="Create redirect" variant="primary" :href="createUrl" />
    </Header>

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        Used only when an address would otherwise be a 404, so a page that exists always wins. “Automatic” ones were added when content moved.
    </p>

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
            <Badge v-if="row.automatic" size="sm" class="ms-2" text="Automatic" />
        </template>
        <template #cell-target="{ row }">
            <span class="font-mono text-sm">{{ row.target ?? '—' }}</span>
        </template>
        <template #cell-active="{ row }">
            <span :class="row.active ? 'text-green-700 dark:text-green-400' : 'text-gray-500'">{{ row.active ? 'Yes' : 'No' }}</span>
        </template>
        <template #cell-last_hit_at="{ row }">
            <When :value="row.last_hit_at" />
        </template>
    </Listing>
</template>
