<script setup>
import { router } from '@statamic/cms/inertia';
import { Badge, Button, Card, Description, Heading, Input } from '@statamic/cms/ui';
import { computed, getCurrentInstance, ref } from 'vue';

/*
 * Connecting Google Search Console, step by step, on Tools → SEO: a key from
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
        toast.error(errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message ?? 'That did not work.'));
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
    toast.success('Copied');
}
</script>

<template>
    <Card class="space-y-4 p-4">
        <div class="flex items-center gap-3">
            <Heading size="lg">Connect Google Search Console</Heading>
            <Badge v-if="setup.configured" color="green" text="Set up" />
        </div>
        <Description>
            Shows how often each page appears in Google, and how often it is clicked. It takes about ten minutes, once.
        </Description>

        <ol class="list-decimal space-y-5 ps-5 text-sm">
            <li class="space-y-2">
                <p>
                    <strong>Create a key in Google Cloud.</strong>
                    In a project (a new one is fine), <a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noopener" class="underline">enable the Google Search Console API</a>,
                    then under <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank" rel="noopener" class="underline">Service accounts</a>
                    create one (it needs no roles), open it → Keys → Add key → JSON. A file downloads.
                </p>
                <div v-if="setup.email" class="flex flex-wrap items-center gap-2">
                    <Badge color="green" text="Key added" />
                    <span class="font-mono text-xs">{{ setup.email }}</span>
                    <span v-if="keyFromEnv" class="text-gray-500">(from .env)</span>
                    <Button v-else size="sm" variant="ghost" text="Remove" :loading="busy === 'forget'" @click="forgetKey" />
                </div>
                <template v-else-if="keyFromEnv">
                    <p class="text-red-600 dark:text-red-400">SEO_SEARCH_CONSOLE_CREDENTIALS in .env is not a service account key, or the file it names can't be read.</p>
                </template>
                <div v-else class="space-y-2">
                    <input ref="fileInput" type="file" accept=".json,application/json" class="hidden" @change="uploadKey" />
                    <Button text="Upload the key file" variant="primary" :loading="busy === 'key'" @click="fileInput.click()" />
                    <details class="text-gray-600 dark:text-gray-400">
                        <summary class="cursor-pointer">Or paste its contents</summary>
                        <textarea v-model="pasted" rows="4" class="mt-2 w-full rounded border border-gray-300 p-2 font-mono text-xs dark:border-gray-700 dark:bg-gray-900" />
                        <Button class="mt-1" size="sm" text="Save the key" :disabled="!pasted" :loading="busy === 'key'" @click="pasteKey" />
                    </details>
                    <p class="text-gray-500">It is stored on the server in storage/app/private, never in git; only its email is shown here.</p>
                </div>
            </li>

            <li class="space-y-2">
                <p>
                    <strong>Let the key read your property.</strong>
                    In <a :href="usersUrl" target="_blank" rel="noopener" class="underline">Search Console → Settings → Users and permissions</a>,
                    add <span v-if="setup.email" class="font-mono text-xs">{{ setup.email }}</span><span v-else>the key's email</span> as a <em>Restricted</em> user.
                </p>
                <Button v-if="setup.email" size="sm" variant="ghost" text="Copy the email" @click="copyEmail" />
            </li>

            <li class="space-y-2">
                <p>
                    <strong>Name the property</strong> as Search Console does: <span class="font-mono text-xs">sc-domain:example.com</span> for a domain,
                    <span class="font-mono text-xs">https://example.com/</span> for an address prefix.
                </p>
                <div v-if="propertyFromEnv" class="flex items-center gap-2">
                    <span class="font-mono text-xs">{{ setup.property }}</span>
                    <span class="text-gray-500">(from .env)</span>
                </div>
                <div v-else class="flex max-w-lg gap-2">
                    <Input v-model="property" class="font-mono" @keydown.enter.prevent="saveProperty" />
                    <Button text="Save" :loading="busy === 'property'" :disabled="!property || property === setup.property" @click="saveProperty" />
                </div>
            </li>

            <li class="space-y-2">
                <p><strong>Check it works</strong>, then bring in the numbers.</p>
                <div class="flex flex-wrap gap-2">
                    <Button text="Check the connection" :disabled="!setup.configured" :loading="busy === 'check'" @click="check" />
                    <Button text="Import now" variant="primary" :disabled="!setup.configured" :loading="busy === 'import'" @click="importNow" />
                </div>
                <p v-if="result" :class="result.ok ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ result.message }}</p>
                <p class="text-gray-500">After that the numbers update daily at 04:30, when Laravel's scheduler runs (<code>php artisan schedule:run</code> every minute). Without it, come back and use Import now.</p>
            </li>
        </ol>
    </Card>
</template>
