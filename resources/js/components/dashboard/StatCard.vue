<script setup>
import AppIcon from '../ui/AppIcon.vue';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    detail: { type: String, default: '' },
    icon: { type: String, required: true },
    tone: { type: String, default: 'brand' },
    to: { type: [String, Object], default: null },
});

const tones = {
    brand: 'bg-brand-50 text-brand-700',
    accent: 'bg-accent-50 text-accent-700',
    warning: 'bg-amber-50 text-amber-700',
    danger: 'bg-red-50 text-red-700',
};
</script>

<template>
    <component
        :is="to ? 'RouterLink' : 'div'"
        :to="to ?? undefined"
        class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs"
        :class="to ? 'transition hover:border-brand-300 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-brand-600' : ''"
    >
        <span class="rounded-lg p-2.5" :class="tones[tone]">
            <AppIcon :name="icon" :size="20" />
        </span>
        <div class="min-w-0">
            <p class="text-sm text-slate-500">{{ label }}</p>
            <p class="mt-0.5 text-2xl font-semibold text-slate-900 tabular-nums">{{ value }}</p>
            <p v-if="detail" class="mt-0.5 truncate text-xs text-slate-500">{{ detail }}</p>
        </div>
    </component>
</template>
