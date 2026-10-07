<script setup>
import { debounce, Fieldtype } from '@statamic/cms';
import { Description, injectPublishContext, Skeleton } from '@statamic/cms/ui';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useAxios } from '../util.js';
import Counter from './Counter.vue';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose } = Fieldtype.use(emit, props);
defineExpose(expose);

const { values, blueprint, reference, site } = injectPublishContext();
const axios = useAxios();

const resolved = ref(null);
const failed = ref(false);
const card = ref(null);
const cardLoading = ref(false);
const cardFailed = ref(false);
let unmounted = false;

const seo = computed(() => values.value?.seo ?? {});
const filled = (value) => typeof value === 'string' && value.trim() !== '';

// What the editor typed shows at once; the rules' fallbacks follow from the server.
const title = computed(() => (filled(seo.value.title) ? seo.value.title : resolved.value?.title) ?? '');
const ogTitle = computed(() => (filled(seo.value.title) ? seo.value.title : resolved.value?.og_title) ?? '');
const description = computed(() => (filled(seo.value.description) ? seo.value.description : resolved.value?.description) ?? '');
const address = computed(() => {
    try {
        return new URL(resolved.value?.url);
    } catch {
        return null;
    }
});
const host = computed(() => address.value?.host ?? '');
const crumbs = computed(() => address.value?.pathname.split('/').filter(Boolean) ?? []);
const image = computed(() => {
    const value = resolved.value?.image;

    if (!value) return null;

    return value.generated ? card.value : value.url;
});
const noindex = computed(() => (resolved.value?.robots ?? '').includes('noindex'));

function payload() {
    return {
        blueprint: blueprint.value?.fqh,
        reference: reference.value,
        site: site.value,
        values: values.value,
    };
}

/**
 * Only the latest of a kind of request counts: starting one cancels the one
 * before it, so a slow, stale answer never overwrites a newer one.
 */
function latest() {
    let controller = null;

    return {
        start() {
            controller?.abort();
            controller = new AbortController();
            return controller;
        },
        // Whether this request's answer still counts.
        current: (mine) => !unmounted && mine === controller,
        cancel: () => controller?.abort(),
    };
}

const metaRequests = latest();
const cardRequests = latest();

const fetchMeta = debounce(async () => {
    const mine = metaRequests.start();

    try {
        const { data } = await axios.post(props.meta.urls.meta, payload(), { signal: mine.signal });
        if (!metaRequests.current(mine)) return;
        resolved.value = data;
        failed.value = false;
    } catch {
        if (metaRequests.current(mine)) failed.value = true;
    }
}, 600);

const fetchCard = debounce(async () => {
    const mine = cardRequests.start();
    cardLoading.value = true;

    try {
        const response = await axios.post(props.meta.urls.card, payload(), { responseType: 'blob', signal: mine.signal });
        if (!cardRequests.current(mine)) return;
        if (card.value) URL.revokeObjectURL(card.value);
        card.value = URL.createObjectURL(response.data);
        cardFailed.value = false;
    } catch {
        if (!cardRequests.current(mine)) return;
        if (card.value) URL.revokeObjectURL(card.value);
        card.value = null;
        cardFailed.value = true;
    } finally {
        if (cardRequests.current(mine)) cardLoading.value = false;
    }
}, 300);

// Where the share image goes while there is none to show.
const placeholder = computed(() => {
    if (cardLoading.value) return __('marketing-toolkit::cp.preview.drawing');
    if (cardFailed.value && resolved.value?.image?.generated) return __('marketing-toolkit::cp.preview.card_failed');
    return __('marketing-toolkit::cp.preview.no_image');
});

watch(values, fetchMeta, { deep: true, immediate: true });

// The card is slower to draw, so redraw it only when its own words change.
const cardKey = computed(() =>
    resolved.value?.image?.generated
        ? JSON.stringify([ogTitle.value, description.value, seo.value.og_title, seo.value.og_subtitle])
        : null,
);
watch(cardKey, (key) => key && props.meta.og && fetchCard());

onBeforeUnmount(() => {
    unmounted = true;
    fetchMeta.cancel();
    fetchCard.cancel();
    metaRequests.cancel();
    cardRequests.cancel();
    if (card.value) URL.revokeObjectURL(card.value);
});
</script>

<template>
    <div v-if="blueprint?.fqh" class="space-y-6">
        <Description v-if="failed" class="text-(--theme-color-danger)">{{ __('marketing-toolkit::cp.preview.failed') }}</Description>

        <!-- Until the first answer: the rules' fallbacks, the address and the image aren't known yet. -->
        <template v-if="!resolved && !failed">
            <Skeleton class="h-32" />
            <div class="grid gap-6 lg:grid-cols-2">
                <Skeleton class="aspect-[1200/630]" />
                <Skeleton class="aspect-[1200/630]" />
            </div>
        </template>

        <template v-else>
            <section>
                <div class="mb-2 flex items-center justify-between gap-4">
                    <h3 class="text-sm font-medium">Google</h3>
                    <div class="flex gap-3 text-xs">
                        <Counter :label="__('marketing-toolkit::cp.preview.title')" :text="title" :limits="meta.limits.title" />
                        <Counter :label="__('marketing-toolkit::cp.preview.description')" :text="description" :limits="meta.limits.description" />
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 font-[arial,sans-serif] dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="flex size-7 items-center justify-center rounded-full bg-gray-100 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ (resolved?.site_name ?? '?').charAt(0) }}</span>
                        <span class="leading-tight">
                            <span class="block text-gray-900 dark:text-gray-100">{{ resolved?.site_name }}</span>
                            <span class="block text-xs text-gray-600 dark:text-gray-400">{{ [address?.origin, ...crumbs].filter(Boolean).join(' › ') }}</span>
                        </span>
                    </div>
                    <p class="mt-1 max-w-[600px] truncate text-xl text-[#1a0dab] dark:text-[#99c3ff]">{{ title }}</p>
                    <p class="mt-1 line-clamp-2 max-w-[600px] text-sm text-gray-700 dark:text-gray-300">{{ description }}</p>
                    <p v-if="noindex" class="mt-2 text-xs text-amber-700 dark:text-amber-400">{{ __('marketing-toolkit::cp.preview.noindex', { robots: resolved.robots }) }}</p>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section>
                    <h3 class="mb-2 text-sm font-medium">Facebook, LinkedIn, WhatsApp</h3>
                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                        <div class="relative aspect-[1200/630] bg-gray-100 dark:bg-gray-800">
                            <img v-if="image" :src="image" :alt="resolved?.image?.alt ?? ''" class="size-full object-cover" />
                            <span v-else class="absolute inset-0 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400">{{ placeholder }}</span>
                        </div>
                        <div class="border-t border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">{{ host }}</p>
                            <p class="truncate font-semibold text-gray-900 dark:text-gray-100">{{ ogTitle }}</p>
                            <p class="truncate text-sm text-gray-600 dark:text-gray-400">{{ description }}</p>
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="mb-2 text-sm font-medium">X</h3>
                    <div class="relative aspect-[1200/630] overflow-hidden rounded-2xl border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                        <img v-if="image" :src="image" :alt="resolved?.image?.alt ?? ''" class="size-full object-cover" />
                        <span v-else class="absolute inset-0 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400">{{ placeholder }}</span>
                    </div>
                    <!-- The title under the picture rather than over it, where it covered the card's own text. -->
                    <p class="mt-1.5 truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ ogTitle }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('marketing-toolkit::cp.preview.from', { host }) }}<template v-if="resolved?.twitter_site"> · {{ resolved.twitter_site }}</template></p>
                </section>
            </div>
        </template>
    </div>
</template>
