<script setup>
/*
 * Paginação baseada no `meta` retornado pela API (current_page, last_page,
 * from, to, total). Exibe uma janela curta de páginas para caber em telas
 * pequenas.
 */
import { computed } from 'vue';
import { formatNumber } from '@/utils/format';
import AppIcon from '../ui/AppIcon.vue';

const props = defineProps({
    meta: { type: Object, default: null },
});

const emit = defineEmits(['change']);

const pages = computed(() => {
    if (!props.meta) {
        return [];
    }

    const { current_page: current, last_page: last } = props.meta;
    const start = Math.max(1, Math.min(current - 2, last - 4));
    const end = Math.min(last, start + 4);

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
});

const buttonClass =
    'inline-flex min-w-9 items-center justify-center rounded-md px-2.5 py-1.5 text-sm focus-visible:outline-2 focus-visible:outline-brand-600 disabled:cursor-not-allowed disabled:opacity-40';
</script>

<template>
    <nav
        v-if="meta && meta.total > 0"
        aria-label="Paginação"
        class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row"
    >
        <p class="text-sm text-slate-500">
            Mostrando <span class="font-medium text-slate-700">{{ meta.from }}</span>–<span class="font-medium text-slate-700">{{ meta.to }}</span>
            de <span class="font-medium text-slate-700">{{ formatNumber(meta.total) }}</span>
        </p>
        <div v-if="meta.last_page > 1" class="flex items-center gap-1">
            <button
                type="button"
                :class="buttonClass"
                class="text-slate-600 hover:bg-slate-100"
                :disabled="meta.current_page <= 1"
                aria-label="Página anterior"
                @click="emit('change', meta.current_page - 1)"
            >
                <AppIcon name="chevronLeft" :size="16" />
            </button>
            <button
                v-for="page in pages"
                :key="page"
                type="button"
                :class="[buttonClass, page === meta.current_page ? 'bg-brand-900 font-medium text-white' : 'text-slate-600 hover:bg-slate-100']"
                :aria-current="page === meta.current_page ? 'page' : undefined"
                :aria-label="`Página ${page}`"
                @click="emit('change', page)"
            >
                {{ page }}
            </button>
            <button
                type="button"
                :class="buttonClass"
                class="text-slate-600 hover:bg-slate-100"
                :disabled="meta.current_page >= meta.last_page"
                aria-label="Próxima página"
                @click="emit('change', meta.current_page + 1)"
            >
                <AppIcon name="chevronRight" :size="16" />
            </button>
        </div>
    </nav>
</template>
