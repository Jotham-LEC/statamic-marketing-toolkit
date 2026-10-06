<script setup>
import { router } from '@statamic/cms/inertia';
import { Badge, Button, Card, Description, Heading, Input } from '@statamic/cms/ui';
import { computed, getCurrentInstance, ref } from 'vue';

/*
 * Connecting Google Search Console, step by step, on Tools → SEO → Search Console: a key from
 * Google Cloud, its email added as a user of the property, the property, a
 * check, and the first import. What .env sets is shown and can't be changed here.
 */
const props = defineProps({ setup: { type: Object, required: true } });

const { $axios: axios, $toast: toast } = getCurrentInstance().appContext.config.globalProperties;

const fileInput = ref(null);
const pasted = ref('');
const property = ref(props.setup.property ?? props.setup.suggested_property);
const busy = ref(null);
const result = ref(null);

const keyFromEnv = computed(() => props.setup.key_source === 'env');
const propertyFromEnv = computed(() => props.setup.property_source === 'env');
const escape = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const link = (href, text) => `<a href="${escape(href)}" target="_blank" rel="noopener" class="underline">${escape(text)}</a>`;
const code = (text) => `<span class="font-mono text-xs">${escape(text)}</span>`;

const usersUrl = computed(() =>
    props.setup.property
        ? `https://search.google.com/search-console/users?resource_id=${encodeURIComponent(props.setup.property)}`
        : 'https://search.google.com/search-console',
);

async function send(action, request) {
    busy.value = action;

    try {
        const { data } = await request();
        return data;
    } catch (error) {
        const errors = error.response?.data?.errors;
        toast.error(errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message ?? __('seo::cp.search_console.setup.failed')));
        return null;
    } finally {
        busy.value = null;
    }
}

async function uploadKey(event) {
    const chosen = event.target.files[0];
    event.target.value = '';
    if (!chosen) return;

    const form = new FormData();
    form.append('file', chosen);

    if (await send('key', () => axios.post(props.setup.urls.key, form))) router.reload();
}

async function pasteKey() {
    if (await send('key', () => axios.post(props.setup.urls.key, { key: pasted.value }))) {
        pasted.value = '';
        router.reload();
    }
}

async function forgetKey() {
    if (await send('forget', () => axios.delete(props.setup.urls.forget_key))) router.reload();
}

async function saveProperty() {
    if (await send('property', () => axios.post(props.setup.urls.property, { property: property.value }))) router.reload();
}

async function check() {
    result.value = await send('check', () => axios.post(props.setup.urls.check));
}

async function importNow() {
    result.value = await send('import', () => axios.post(props.setup.urls.import));
    if (result.value?.ok) router.reload();
}

function copyEmail() {
    navigator.clipboard?.writeText(props.setup.email);
    toast.success(__('seo::cp.search_console.setup.copied'));
}
</script>

<template>
    <Card class="space-y-4 p-4">
        <div class="flex items-center gap-3">
            <Heading size="lg">{{ __('seo::cp.search_console.setup.heading') }}</Heading>
            <Badge v-if="setup.configured" color="green" :text="__('seo::cp.search_console.setup.set_up')" />
        </div>
        <Description>{{ __('seo::cp.search_console.setup.intro') }}</Description>

        <ol class="list-decimal space-y-5 ps-5 text-sm">
            <li class="space-y-2">
                <p>
                    <strong>{{ __('seo::cp.search_console.setup.step_key') }}</strong>
                    <span
                        v-html="
                            __('seo::cp.search_console.setup.step_key_body', {
                                enable: link('https://console.cloud.google.com/apis/library/searchconsole.googleapis.com', __('seo::cp.search_console.setup.enable_api')),
                                accounts: link('https://console.cloud.google.com/iam-admin/serviceaccounts', __('seo::cp.search_console.setup.service_accounts')),
                            })
                        "
                    />
                </p>
                <p
                    class="text-gray-600 dark:text-gray-400"
                    v-html="
                        __('seo::cp.search_console.setup.key_blocked', {
                            policy: link('https://console.cloud.google.com/iam-admin/orgpolicies/iam-disableServiceAccountKeyCreation', __('seo::cp.search_console.setup.key_policy')),
                            enable: link('https://docs.cloud.google.com/iam/docs/keys-disable-enable', __('seo::cp.search_console.setup.key_enable')),
                        })
                    "
                />
                <div v-if="setup.email" class="flex flex-wrap items-center gap-2">
                    <Badge color="green" :text="__('seo::cp.search_console.setup.key_added')" />
                    <span class="font-mono text-xs">{{ setup.email }}</span>
                    <span v-if="keyFromEnv" class="text-gray-500">{{ __('seo::cp.search_console.from_env') }}</span>
                    <Button v-else size="sm" variant="ghost" :text="__('seo::cp.search_console.setup.remove')" :loading="busy === 'forget'" @click="forgetKey" />
                </div>
                <template v-else-if="keyFromEnv">
                    <p class="text-red-600 dark:text-red-400">{{ __('seo::cp.search_console.setup.env_key_invalid') }}</p>
                </template>
                <div v-else class="space-y-2">
                    <input ref="fileInput" type="file" accept=".json,application/json" class="hidden" @change="uploadKey" />
                    <Button :text="__('seo::cp.search_console.setup.upload')" variant="primary" :loading="busy === 'key'" @click="fileInput.click()" />
                    <details class="text-gray-600 dark:text-gray-400">
                        <summary class="cursor-pointer">{{ __('seo::cp.search_console.setup.paste') }}</summary>
                        <textarea v-model="pasted" rows="4" class="mt-2 w-full rounded border border-gray-300 p-2 font-mono text-xs dark:border-gray-700 dark:bg-gray-900" />
                        <Button class="mt-1" size="sm" :text="__('seo::cp.search_console.setup.save_key')" :disabled="!pasted" :loading="busy === 'key'" @click="pasteKey" />
                    </details>
                    <p class="text-gray-500">{{ __('seo::cp.search_console.setup.stored') }}</p>
                </div>
            </li>

            <li class="space-y-2">
                <p>
                    <strong>{{ __('seo::cp.search_console.setup.step_users') }}</strong>
                    <span
                        v-html="
                            __('seo::cp.search_console.setup.step_users_body', {
                                users: link(usersUrl, __('seo::cp.search_console.setup.users_link')),
                                email: setup.email ? code(setup.email) : __('seo::cp.search_console.setup.the_email'),
                            })
                        "
                    />
                </p>
                <Button v-if="setup.email" size="sm" variant="ghost" :text="__('seo::cp.search_console.setup.copy_email')" @click="copyEmail" />
            </li>

            <li class="space-y-2">
                <p>
                    <strong>{{ __('seo::cp.search_console.setup.step_property') }}</strong>
                    <span v-html="__('seo::cp.search_console.setup.step_property_body', { domain: code('sc-domain:example.com'), prefix: code('https://example.com/') })" />
                </p>
                <div v-if="propertyFromEnv" class="flex items-center gap-2">
                    <span class="font-mono text-xs">{{ setup.property }}</span>
                    <span class="text-gray-500">{{ __('seo::cp.search_console.from_env') }}</span>
                </div>
                <div v-else class="flex max-w-lg gap-2">
                    <Input v-model="property" class="font-mono" @keydown.enter.prevent="saveProperty" />
                    <Button :text="__('seo::cp.search_console.setup.save')" :loading="busy === 'property'" :disabled="!property || property === setup.property" @click="saveProperty" />
                </div>
            </li>

            <li class="space-y-2">
                <p><strong>{{ __('seo::cp.search_console.setup.step_check') }}</strong>{{ __('seo::cp.search_console.setup.step_check_body') }}</p>
                <div class="flex flex-wrap gap-2">
                    <Button :text="__('seo::cp.search_console.setup.check')" :disabled="!setup.configured" :loading="busy === 'check'" @click="check" />
                    <Button :text="__('seo::cp.search_console.import')" variant="primary" :disabled="!setup.configured" :loading="busy === 'import'" @click="importNow" />
                </div>
                <p v-if="result" :class="result.ok ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ result.message }}</p>
                <p class="text-gray-500" v-html="__('seo::cp.search_console.setup.schedule', { command: '<code>php artisan schedule:run</code>' })" />
            </li>
        </ol>
    </Card>
</template>
