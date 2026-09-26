<script setup>
/*
 * Exibe um status {value, label} vindo da API. O texto é sempre o label do
 * backend; aqui só se decide a cor, agrupando os valores dos diferentes enums
 * (apólice, parcela, sinistro) por significado visual.
 */
import { computed } from 'vue';

const props = defineProps({
    status: { type: Object, default: null },
});

const tones = {
    success: ['active', 'paid', 'approved'],
    info: ['quote', 'pending', 'open', 'under_review'],
    warning: ['suspended', 'pending_documents', 'overdue'],
    danger: ['cancelled', 'denied'],
    neutral: ['expired', 'closed'],
};

const toneClasses = {
    success: 'bg-accent-50 text-accent-700 ring-accent-600/20',
    info: 'bg-brand-50 text-brand-700 ring-brand-600/20',
    warning: 'bg-amber-50 text-amber-800 ring-amber-600/25',
    danger: 'bg-red-50 text-red-700 ring-red-600/20',
    neutral: 'bg-slate-100 text-slate-600 ring-slate-500/20',
};

const tone = computed(() => {
    const entry = Object.entries(tones).find(([, values]) => values.includes(props.status?.value));

    return entry ? entry[0] : 'neutral';
});
</script>

<template>
    <span
        v-if="status"
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset"
        :class="toneClasses[tone]"
    >
        {{ status.label }}
    </span>
</template>
