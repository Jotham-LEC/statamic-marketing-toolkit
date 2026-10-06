<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useAxios } from '../util.js';

const props = defineProps({ report: { type: Object, required: true } });
const emit = defineEmits(['done']);

const axios = useAxios();
const current = ref(props.report);
let stopped = false;

// Each request also runs the next step when the site has no queue worker.
async function poll() {
    while (!stopped && current.value.status === 'running') {
        try {
            current.value = (await axios.post(current.value.progress_url)).data;
        } catch {
            await new Promise((resolve) => setTimeout(resolve, 3000));
        }

        if (current.value.status === 'running') await new Promise((resolve) => setTimeout(resolve, 800));
    }

    if (!stopped) emit('done', current.value);
}

onMounted(poll);
onBeforeUnmount(() => (stopped = true));
</script>

<template>
    <div>
        <div class="mb-1 flex justify-between text-sm">
            <span>{{ __('seo::reports.cp.checking') }}</span>
            <span>{{ current.pages_done }} / {{ current.pages_total }}</span>
        </div>
        <div
            class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
            role="progressbar"
            :aria-label="__('seo::reports.cp.checking')"
            aria-valuemin="0"
            :aria-valuemax="current.pages_total"
            :aria-valuenow="current.pages_done"
        >
            <div class="h-full bg-blue-600 transition-all" :style="{ width: `${current.pages_total ? (100 * current.pages_done) / current.pages_total : 0}%` }" />
        </div>
    </div>
</template>
