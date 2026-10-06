<script setup>
import { Head, router } from '@statamic/cms/inertia';
import { Badge, Button, Card, Description, Header, Heading } from '@statamic/cms/ui';
import { getCurrentInstance, ref } from 'vue';
import SearchConsoleSetup from '../components/SearchConsoleSetup.vue';
import When from '../components/When.vue';

/*
 * Tools → SEO → Search Console: the steps to connect it, then where the
 * connection stands, with Import now and Disconnect.
 */
const props = defineProps({
    setup: { type: Object, required: true },
    sites: { type: Array, default: () => [] },
    imported: { type: Object, required: true },
    overviewUrl: { type: String, required: true },
});

const { $axios: axios, $toast: toast } = getCurrentInstance().appContext.config.globalProperties;
const busy = ref(null);
const result = ref(null);
// Once connected and imported, the steps fold away behind a button.
const showSetup = ref(props.setup.can_set_up && !(props.setup.configured && props.imported.fetched_at));

async function importNow() {
    busy.value = 'import';

    try {
        const { data } = await axios.post(props.setup.urls.import);
        result.value = data;
        if (data.ok) router.reload();
    } catch (error) {
        toast.error(error.response?.data?.message ?? __('seo::cp.search_console.setup.failed'));
    } finally {
        busy.value = null;
    }
}

async function disconnect() {
    if (!confirm(__('seo::cp.search_console.disconnect_confirm'))) return;

    busy.value = 'disconnect';

    try {
        await axios.delete(props.setup.urls.forget_key);
        router.reload();
    } catch (error) {
        toast.error(error.response?.data?.message ?? __('seo::cp.search_console.setup.failed'));
    } finally {
        busy.value = null;
    }
}
</script>

<template>
    <Head :title="__('seo::cp.search_console.title')" />

    <div class="max-w-page mx-auto space-y-6">
        <Header :title="__('seo::cp.search_console.title')" icon="search-magnifying-glass">
            <Button :href="overviewUrl" :text="__('seo::cp.search_console.overview')" variant="ghost" />
        </Header>

        <Card class="space-y-3 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Heading size="lg">{{ __('seo::cp.search_console.status') }}</Heading>
                    <Badge v-if="setup.configured" color="green" :text="__('seo::cp.search_console.connected')" />
                    <Badge v-else :text="__('seo::cp.search_console.not_connected')" />
                </div>
                <div v-if="setup.can_set_up" class="flex flex-wrap gap-2">
                    <Button v-if="setup.configured" :text="__('seo::cp.search_console.import')" variant="primary" :loading="busy === 'import'" @click="importNow" />
                    <Button v-if="setup.email && setup.key_source === 'cp'" :text="__('seo::cp.search_console.disconnect')" variant="danger" :loading="busy === 'disconnect'" @click="disconnect" />
                    <Button v-if="setup.configured && imported.fetched_at" variant="ghost" :text="showSetup ? __('seo::cp.search_console.setup.heading') + ' ▴' : __('seo::cp.search_console.setup.heading') + ' ▾'" @click="showSetup = !showSetup" />
                </div>
            </div>

            <Description v-if="!setup.configured && !setup.can_set_up">{{ __('seo::cp.search_console.not_connected_body') }}</Description>

            <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
                <template v-if="setup.can_set_up">
                    <dt class="text-gray-500">{{ __('seo::cp.search_console.key') }}</dt>
                    <dd class="font-mono text-xs">
                        {{ setup.email ?? '—' }}
                        <span v-if="setup.key_source === 'env'" class="font-sans text-gray-500">{{ __('seo::cp.search_console.from_env') }}</span>
                    </dd>
                    <template v-if="!sites.length">
                        <dt class="text-gray-500">{{ __('seo::cp.search_console.property') }}</dt>
                        <dd class="font-mono text-xs">{{ setup.property ?? '—' }}</dd>
                    </template>
                </template>
                <dt class="text-gray-500">{{ __('seo::cp.search_console.last_import') }}</dt>
                <dd><When v-if="imported.fetched_at" :value="imported.fetched_at" /><span v-else>{{ __('seo::cp.search_console.never') }}</span></dd>
                <dt class="text-gray-500">{{ __('seo::cp.search_console.pages') }}</dt>
                <dd class="tabular-nums">{{ imported.pages }}</dd>
            </dl>

            <template v-if="sites.length && setup.can_set_up">
                <Heading size="sm">{{ __('seo::cp.search_console.properties') }}</Heading>
                <ul class="space-y-1 text-sm">
                    <li v-for="site in sites" :key="site.name" class="flex gap-3" :class="{ 'font-semibold': site.selected }">
                        <span class="min-w-32">{{ site.name }}</span>
                        <span class="font-mono text-xs">{{ site.property ?? __('seo::cp.search_console.no_property') }}</span>
                    </li>
                </ul>
            </template>

            <p v-if="result" :class="result.ok ? 'text-sm text-green-700 dark:text-green-400' : 'text-sm text-red-600 dark:text-red-400'">{{ result.message }}</p>
        </Card>

        <SearchConsoleSetup v-if="showSetup" :setup="setup" />
    </div>
</template>
