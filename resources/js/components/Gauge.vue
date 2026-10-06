<script setup>
import { computed } from 'vue';

/*
 * A score out of 100 as a half-circle gauge: ten bands from red to green,
 * a marker at the score, and the number in the middle.
 */
const props = defineProps({
    value: { type: Number, default: null },
    size: { type: String, default: 'md' }, // sm, md or lg
    label: { type: String, default: null },
});

const center = { x: 100, y: 100 };
const inner = 62;
const outer = 92;
const bands = 10;
const colors = ['#dc2626', '#e8492a', '#f0702c', '#f59e0b', '#f5b70b', '#e3c10e', '#b7c22a', '#84bd3a', '#4fae44', '#16a34a'];

function point(radius, angle) {
    const radians = (Math.PI * angle) / 180;

    return [center.x + radius * Math.cos(radians), center.y - radius * Math.sin(radians)];
}

// Each band an arc segment, 180° (0) to 0° (100), with a small gap between them.
const segments = computed(() =>
    Array.from({ length: bands }, (_, index) => {
        const from = 180 - (180 / bands) * index - 0.8;
        const to = 180 - (180 / bands) * (index + 1) + 0.8;
        const [x1, y1] = point(outer, from);
        const [x2, y2] = point(outer, to);
        const [x3, y3] = point(inner, to);
        const [x4, y4] = point(inner, from);

        return {
            d: `M${x1} ${y1} A${outer} ${outer} 0 0 1 ${x2} ${y2} L${x3} ${y3} A${inner} ${inner} 0 0 0 ${x4} ${y4} Z`,
            color: colors[index],
        };
    }),
);

const clamped = computed(() => (props.value === null ? null : Math.max(0, Math.min(100, props.value))));
const marker = computed(() => (clamped.value === null ? null : point(inner - 7, 180 - 1.8 * clamped.value)));
const ticks = [0, 20, 40, 60, 80, 100].map((tick) => ({ tick, at: point(outer + 11, 180 - 1.8 * tick) }));
const width = computed(() => ({ sm: 'w-28', md: 'w-44', lg: 'w-64' })[props.size] ?? 'w-44');
</script>

<template>
    <figure :class="width" class="inline-block text-center" role="meter" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="clamped ?? undefined" :aria-label="label ?? __('seo::cp.gauge.label')">
        <!-- The meter carries the value; the drawing is only its picture. -->
        <svg viewBox="0 -12 200 118" class="w-full overflow-visible" aria-hidden="true">
            <path v-for="(segment, index) in segments" :key="index" :d="segment.d" :fill="segment.color" :opacity="clamped === null ? 0.25 : 1" />
            <template v-if="size !== 'sm'">
                <text v-for="{ tick, at } in ticks" :key="tick" :x="at[0]" :y="at[1]" font-size="11" text-anchor="middle" class="fill-gray-500">{{ tick }}</text>
            </template>
            <circle v-if="marker" :cx="marker[0]" :cy="marker[1]" r="4.5" class="fill-gray-900 dark:fill-white" />
            <text x="100" y="98" text-anchor="middle" font-size="40" font-weight="600" class="fill-gray-900 tabular-nums dark:fill-white">{{ clamped ?? '—' }}</text>
        </svg>
        <figcaption v-if="label" class="mt-1 text-xs uppercase tracking-wide text-gray-500">{{ label }}</figcaption>
    </figure>
</template>
