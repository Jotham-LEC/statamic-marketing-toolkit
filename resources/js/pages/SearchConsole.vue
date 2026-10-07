<script setup>
import { Head, router } from '@statamic/cms/inertia';
import { Badge, Button, Card, ConfirmationModal, Description, Header, Heading } from '@statamic/cms/ui';
import { ref } from 'vue';
import SearchConsoleSetup from '../components/SearchConsoleSetup.vue';
import When from '../components/When.vue';
import { useRequests } from '../util.js';

/*
 * Marketing → Search Console: the steps to connect it, then where the
 * connection stands, with Import now and Disconnect.
 */
const props = defineProps({
    setup: { type: Object, required: true },
    sites: { type: Array, default: () => [] },
    imported: { type: Object, required: true },
    overviewUrl: { type: String, required: true },
});

const { busy, send } = useRequests(__('marketing-toolkit::cp.search_console.setup.failed'));
const result = ref(null);
const confirming = ref(false);
// Once connected and imported, the steps fold away behind a button.
const showSetup = ref(props.setup.can_set_up && !(props.setup.configured && props.imported.fetched_at));

async function importNow() {
    result.value = await send('import', (axios) => axios.post(props.setup.urls.import));
    if (result.value?.ok) router.reload();
}

async function disconnect() {
    if (await send('disconnect', (axios) => axios.delete(props.setup.urls.forget_key))) router.reload();
}
</script>

<template>
    <Head :title="__('marketing-toolkit::cp.search_console.title')" />

    <div class="max-w-page mx-auto space-y-6">
        <Header :title="__('marketing-toolkit::cp.search_console.title')" icon="search-magnifying-glass">
            <Button :href="overviewUrl" :text="__('marketing-toolkit::cp.search_console.overview')" variant="ghost" />
        </Header>

        <Card class="space-y-3 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Heading size="lg">{{ __('marketing-toolkit::cp.search_console.status') }}</Heading>
                    <Badge v-if="setup.configured" color="green" :text="__('marketing-toolkit::cp.search_console.connected')" />
                    <Badge v-else :text="__('marketing-toolkit::cp.search_console.not_connected')" />
                </div>
                <div v-if="setup.can_set_up" class="flex flex-wrap gap-2">
                    <Button v-if="setup.configured" :text="__('marketing-toolkit::cp.search_console.import')" variant="primary" :loading="busy === 'import'" @click="importNow" />
                    <Button v-if="setup.email && setup.key_source === 'cp'" :text="__('marketing-toolkit::cp.search_console.disconnect')" variant="danger" :loading="busy === 'disconnect'" @click="confirming = true" />
                    <Button
                        v-if="setup.configured && imported.fetched_at"
                        variant="ghost"
                        :text="__('marketing-toolkit::cp.search_console.setup.heading')"
                        :icon-append="showSetup ? 'chevron-up' : 'chevron-down'"
                        :aria-expanded="showSetup"
                        @click="showSetup = !showSetup"
                    />
                </div>
            </div>

            <Description v-if="!setup.configured && !setup.can_set_up">{{ __('marketing-toolkit::cp.search_console.not_connected_body') }}</Description>

            <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
                <template v-if="setup.can_set_up">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('marketing-toolkit::cp.search_console.key') }}</dt>
                    <dd class="font-mono text-xs break-all">
                        {{ setup.email ?? '—' }}
                        <span v-if="setup.key_source === 'env'" class="font-sans text-gray-500 dark:text-gray-400">{{ __('marketing-toolkit::cp.search_console.from_env') }}</span>
                    </dd>
                    <template v-if="!sites.length">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('marketing-toolkit::cp.search_console.property') }}</dt>
                        <dd class="font-mono text-xs break-all">{{ setup.property ?? '—' }}</dd>
                    </template>
                </template>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('marketing-toolkit::cp.search_console.last_import') }}</dt>
                <dd><When v-if="imported.fetched_at" :value="imported.fetched_at" /><span v-else>{{ __('marketing-toolkit::cp.search_console.never') }}</span></dd>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('marketing-toolkit::cp.search_console.pages') }}</dt>
                <dd class="tabular-nums">{{ imported.pages }}</dd>
            </dl>

            <template v-if="sites.length">
                <Heading size="sm">{{ __('marketing-toolkit::cp.search_console.properties') }}</Heading>
                <ul class="space-y-1 text-sm">
                    <li v-for="site in sites" :key="site.name" class="flex gap-3" :class="{ 'font-semibold': site.selected }">
                        <span class="min-w-32">{{ site.name }}</span>
                        <span v-if="site.property" class="min-w-0 font-mono text-xs break-all">{{ site.property }}</span>
                        <span v-else>{{ site.connected ? __('marketing-toolkit::cp.search_console.connected') : __('marketing-toolkit::cp.search_console.no_property') }}</span>
                    </li>
                </ul>
            </template>

            <p role="status" class="text-sm" :class="result?.ok ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ result?.message }}</p>
        </Card>

        <SearchConsoleSetup v-if="showSetup" :setup="setup" />
    </div>

    <ConfirmationModal
        v-model:open="confirming"
        :title="__('marketing-toolkit::cp.search_console.disconnect')"
        :body-text="__('marketing-toolkit::cp.search_console.disconnect_confirm')"
        :button-text="__('marketing-toolkit::cp.search_console.disconnect')"
        danger
        @confirm="disconnect"
    />
</template>
