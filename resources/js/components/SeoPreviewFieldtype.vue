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

const fetchMeta = debounce(async () => {
    try {
        resolved.value = (await axios.post(props.meta.urls.meta, payload())).data;
        failed.value = false;
    } catch {
        failed.value = true;
    }
}, 600);

const fetchCard = debounce(async () => {
    cardLoading.value = true;

    try {
        const response = await axios.post(props.meta.urls.card, payload(), { responseType: 'blob' });
        if (card.value) URL.revokeObjectURL(card.value);
        card.value = URL.createObjectURL(response.data);
    } catch {
        card.value = null;
    } finally {
        cardLoading.value = false;
    }
}, 300);

watch(values, fetchMeta, { deep: true, immediate: true });

// The card is slower to draw, so redraw it only when its own words change.
const cardKey = computed(() =>
    resolved.value?.image?.generated
        ? JSON.stringify([ogTitle.value, description.value, seo.value.og_title, seo.value.og_subtitle])
        : null,
);
watch(cardKey, (key) => key && props.meta.og && fetchCard());

onBeforeUnmount(() => {
    fetchMeta.cancel();
    fetchCard.cancel();
    if (card.value) URL.revokeObjectURL(card.value);
});
</script>

<template>
    <div v-if="blueprint?.fqh" class="space-y-6">
        <Description v-if="failed" class="text-(--theme-color-danger)">{{ __('seo::cp.preview.failed') }}</Description>

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
                        <Counter :label="__('seo::cp.preview.title')" :text="title" :limits="meta.limits.title" />
                        <Counter :label="__('seo::cp.preview.description')" :text="description" :limits="meta.limits.description" />
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
                    <p v-if="noindex" class="mt-2 text-xs text-amber-700 dark:text-amber-400">{{ __('seo::cp.preview.noindex', { robots: resolved.robots }) }}</p>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section>
                    <h3 class="mb-2 text-sm font-medium">Facebook, LinkedIn, WhatsApp</h3>
                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                        <div class="relative aspect-[1200/630] bg-gray-100 dark:bg-gray-800">
                            <img v-if="image" :src="image" :alt="resolved?.image?.alt ?? ''" class="size-full object-cover" />
                            <span v-else class="absolute inset-0 flex items-center justify-center text-xs text-gray-500">{{ cardLoading ? __('seo::cp.preview.drawing') : __('seo::cp.preview.no_image') }}</span>
                        </div>
                        <div class="border-t border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs uppercase text-gray-500">{{ host }}</p>
                            <p class="truncate font-semibold text-gray-900 dark:text-gray-100">{{ ogTitle }}</p>
                            <p class="truncate text-sm text-gray-600 dark:text-gray-400">{{ description }}</p>
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="mb-2 text-sm font-medium">X</h3>
                    <div class="relative aspect-[1200/630] overflow-hidden rounded-2xl border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                        <img v-if="image" :src="image" :alt="resolved?.image?.alt ?? ''" class="size-full object-cover" />
                        <span v-else class="absolute inset-0 flex items-center justify-center text-xs text-gray-500">{{ cardLoading ? __('seo::cp.preview.drawing') : __('seo::cp.preview.no_image') }}</span>
                    </div>
                    <!-- The title under the picture rather than over it, where it covered the card's own text. -->
                    <p class="mt-1.5 truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ ogTitle }}</p>
                    <p class="text-xs text-gray-500">{{ __('seo::cp.preview.from', { host }) }}<template v-if="resolved?.twitter_site"> · {{ resolved.twitter_site }}</template></p>
                </section>
            </div>
        </template>
    </div>
</template>
