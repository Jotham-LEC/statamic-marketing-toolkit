<script setup>
import { Button } from '@statamic/cms/ui';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useAxios } from '../util.js';

const props = defineProps({ report: { type: Object, required: true } });
const emit = defineEmits(['done']);

// This is how many failed requests in a row are allowed before giving up. A 4xx
// response (an expired session, or a deleted report) gives up at once, as asking
// again won't change it.
const ATTEMPTS = 3;

const axios = useAxios();
const current = ref(props.report);
const failed = ref(false);
let stopped = false;

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Each request also runs the next step when the site has no queue worker.
async function poll() {
    let failures = 0;
    failed.value = false;

    while (!stopped && current.value.status === 'running') {
        try {
            current.value = (await axios.post(current.value.progress_url)).data;
            failures = 0;
        } catch (error) {
            const status = error.response?.status ?? 0;

            if ((status >= 400 && status < 500) || ++failures >= ATTEMPTS) {
                failed.value = true;
                return;
            }

            await wait(3000);
            continue;
        }

        // A user who may not run reports can only watch. On the sync queue, the report
        // moves on while a user who may run reports has it open, so we ask less often.
        if (current.value.status === 'running') await wait(current.value.advancing === false ? 5000 : 800);
    }

    if (!stopped) emit('done', current.value);
}

onMounted(poll);
onBeforeUnmount(() => (stopped = true));
</script>

<template>
    <div>
        <div class="mb-1 flex justify-between text-sm">
            <span>{{ __('marketing-toolkit::reports.cp.checking') }}</span>
            <span aria-live="polite">{{ current.pages_done }} / {{ current.pages_total }}</span>
        </div>
        <div
            class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
            role="progressbar"
            :aria-label="__('marketing-toolkit::reports.cp.checking')"
            aria-valuemin="0"
            :aria-valuemax="current.pages_total"
            :aria-valuenow="current.pages_done"
        >
            <div class="h-full bg-blue-600 transition-all" :style="{ width: `${current.pages_total ? (100 * current.pages_done) / current.pages_total : 0}%` }" />
        </div>
        <div v-if="failed" role="alert" class="mt-3 flex flex-wrap items-center gap-3 text-sm text-(--theme-color-danger)">
            <span>{{ __('marketing-toolkit::cp.report_progress.failed') }}</span>
            <Button size="sm" :text="__('marketing-toolkit::cp.report_progress.retry')" @click="poll" />
        </div>
        <p v-else-if="current.advancing === false" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
            {{ __('marketing-toolkit::cp.report_progress.watching') }}
        </p>
    </div>
</template>
