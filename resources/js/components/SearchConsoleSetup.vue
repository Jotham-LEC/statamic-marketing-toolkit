<script setup>
import { router } from '@statamic/cms/inertia';
import { toast } from '@statamic/cms/api';
import { Badge, Button, Card, Description, Heading, Input, Textarea } from '@statamic/cms/ui';
import { computed, ref } from 'vue';
import { useRequests } from '../util.js';

/*
 * This walks through connecting Google Search Console, step by step, on Marketing → Search
 * Console. The steps are a key from Google Cloud, adding its email as a user of the
 * property, the property itself, and a check. The import belongs to the page, not to this
 * component. Values that .env sets are shown, but they can't be changed here.
 */
const props = defineProps({ setup: { type: Object, required: true } });

const { busy, send } = useRequests(__('marketing-toolkit::cp.search_console.setup.failed'));
const fileInput = ref(null);
const pasted = ref('');
const property = ref(props.setup.property ?? props.setup.suggested_property);
const result = ref(null);

const keyFromEnv = computed(() => props.setup.key_source === 'env');
const propertyFromEnv = computed(() => props.setup.property_source === 'env');
const escape = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const link = (href, text) => `<a href="${escape(href)}" target="_blank" rel="noopener" class="underline">${escape(text)}</a>`;
const code = (text) => `<span class="font-mono text-xs break-all">${escape(text)}</span>`;

const usersUrl = computed(() =>
    props.setup.property
        ? `https://search.google.com/search-console/users?resource_id=${encodeURIComponent(props.setup.property)}`
        : 'https://search.google.com/search-console',
);

async function uploadKey(event) {
    const chosen = event.target.files[0];
    event.target.value = '';
    if (!chosen) return;

    const form = new FormData();
    form.append('file', chosen);

    if (await send('key', (axios) => axios.post(props.setup.urls.key, form))) router.reload();
}

async function pasteKey() {
    if (await send('key', (axios) => axios.post(props.setup.urls.key, { key: pasted.value }))) {
        pasted.value = '';
        router.reload();
    }
}

async function forgetKey() {
    if (await send('forget', (axios) => axios.delete(props.setup.urls.forget_key))) router.reload();
}

async function saveProperty() {
    if (await send('property', (axios) => axios.post(props.setup.urls.property, { property: property.value }))) router.reload();
}

async function check() {
    result.value = await send('check', (axios) => axios.post(props.setup.urls.check));
}

// The clipboard is only available on https (or localhost), and the browser may refuse access.
async function copyEmail() {
    try {
        if (!navigator.clipboard) throw new Error('No clipboard');
        await navigator.clipboard.writeText(props.setup.email);
        toast.success(__('marketing-toolkit::cp.search_console.setup.copied'));
    } catch {
        toast.error(__('marketing-toolkit::cp.search_console.setup.copy_failed'));
    }
}
</script>

<template>
    <Card class="space-y-4 p-4">
        <div class="flex items-center gap-3">
            <Heading size="lg">{{ __('marketing-toolkit::cp.search_console.setup.heading') }}</Heading>
            <Badge v-if="setup.configured" color="green" :text="__('marketing-toolkit::cp.search_console.setup.set_up')" />
        </div>
        <Description>{{ __('marketing-toolkit::cp.search_console.setup.intro') }}</Description>

        <ol class="list-decimal space-y-5 ps-5 text-sm break-words">
            <li class="space-y-2">
                <p>
                    <strong class="me-1">{{ __('marketing-toolkit::cp.search_console.setup.step_key') }}</strong>
                    <span
                        v-html="
                            __('marketing-toolkit::cp.search_console.setup.step_key_body', {
                                enable: link('https://console.cloud.google.com/apis/library/searchconsole.googleapis.com', __('marketing-toolkit::cp.search_console.setup.enable_api')),
                                accounts: link('https://console.cloud.google.com/iam-admin/serviceaccounts', __('marketing-toolkit::cp.search_console.setup.service_accounts')),
                            })
                        "
                    />
                </p>
                <p
                    class="text-gray-600 dark:text-gray-400"
                    v-html="
                        __('marketing-toolkit::cp.search_console.setup.key_blocked', {
                            policy: link(setup.guides.key_policy, __('marketing-toolkit::cp.search_console.setup.key_policy')),
                            enable: link(setup.guides.keys, __('marketing-toolkit::cp.search_console.setup.key_enable')),
                        })
                    "
                />
                <div v-if="setup.email" class="flex flex-wrap items-center gap-2">
                    <Badge color="green" :text="__('marketing-toolkit::cp.search_console.setup.key_added')" />
                    <span class="font-mono text-xs break-all">{{ setup.email }}</span>
                    <span v-if="keyFromEnv" class="text-gray-500 dark:text-gray-400">{{ __('marketing-toolkit::cp.search_console.from_env') }}</span>
                    <Button v-else size="sm" variant="ghost" :text="__('marketing-toolkit::cp.search_console.setup.remove')" :loading="busy === 'forget'" @click="forgetKey" />
                </div>
                <template v-else-if="keyFromEnv">
                    <p class="text-(--theme-color-danger)">{{ __('marketing-toolkit::cp.search_console.setup.env_key_invalid') }}</p>
                </template>
                <div v-else class="space-y-2">
                    <input ref="fileInput" type="file" accept=".json,application/json" class="hidden" @change="uploadKey" />
                    <Button :text="__('marketing-toolkit::cp.search_console.setup.upload')" variant="primary" :loading="busy === 'key'" @click="fileInput.click()" />
                    <details class="space-y-2 text-gray-600 dark:text-gray-400">
                        <summary class="cursor-pointer">{{ __('marketing-toolkit::cp.search_console.setup.paste') }}</summary>
                        <label for="mt-search-console-key" class="sr-only">{{ __('marketing-toolkit::cp.search_console.setup.paste') }}</label>
                        <Textarea id="mt-search-console-key" v-model="pasted" class="font-mono text-xs" />
                        <Button size="sm" :text="__('marketing-toolkit::cp.search_console.setup.save_key')" :disabled="!pasted" :loading="busy === 'key'" @click="pasteKey" />
                    </details>
                    <p class="text-gray-500 dark:text-gray-400">{{ __('marketing-toolkit::cp.search_console.setup.stored') }}</p>
                </div>
            </li>

            <li class="space-y-2">
                <p>
                    <strong class="me-1">{{ __('marketing-toolkit::cp.search_console.setup.step_users') }}</strong>
                    <span
                        v-html="
                            __('marketing-toolkit::cp.search_console.setup.step_users_body', {
                                users: link(usersUrl, __('marketing-toolkit::cp.search_console.setup.users_link')),
                                email: setup.email ? code(setup.email) : __('marketing-toolkit::cp.search_console.setup.the_email'),
                            })
                        "
                    />
                </p>
                <Button v-if="setup.email" size="sm" variant="ghost" :text="__('marketing-toolkit::cp.search_console.setup.copy_email')" @click="copyEmail" />
            </li>

            <li class="space-y-2">
                <p>
                    <strong class="me-1">{{ __('marketing-toolkit::cp.search_console.setup.step_property') }}</strong>
                    <span v-html="__('marketing-toolkit::cp.search_console.setup.step_property_body', { domain: code('sc-domain:example.com'), prefix: code('https://example.com/') })" />
                </p>
                <div v-if="propertyFromEnv" class="flex items-center gap-2">
                    <span class="font-mono text-xs">{{ setup.property }}</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('marketing-toolkit::cp.search_console.from_env') }}</span>
                </div>
                <div v-else class="flex max-w-lg gap-2">
                    <label for="mt-search-console-property" class="sr-only">{{ __('marketing-toolkit::cp.search_console.property') }}</label>
                    <Input id="mt-search-console-property" v-model="property" class="font-mono" @keydown.enter.prevent="saveProperty" />
                    <Button :text="__('marketing-toolkit::cp.search_console.setup.save')" :loading="busy === 'property'" :disabled="!property || property === setup.property" @click="saveProperty" />
                </div>
            </li>

            <li class="space-y-2">
                <p><strong class="me-1">{{ __('marketing-toolkit::cp.search_console.setup.step_check') }}</strong>{{ __('marketing-toolkit::cp.search_console.setup.step_check_body') }}</p>
                <Button :text="__('marketing-toolkit::cp.search_console.setup.check')" :disabled="!setup.configured" :loading="busy === 'check'" @click="check" />
                <p role="status" :class="result?.ok ? 'text-green-700 dark:text-green-400' : 'text-(--theme-color-danger)'">{{ result?.message }}</p>
                <p class="text-gray-500 dark:text-gray-400" v-html="__('marketing-toolkit::cp.search_console.setup.schedule', { command: '<code>php artisan schedule:run</code>' })" />
            </li>
        </ol>
    </Card>
</template>
