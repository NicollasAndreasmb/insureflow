<script setup>
/*
 * Diálogo modal acessível: role="dialog" com aria-modal, título associado,
 * foco movido para dentro ao abrir e devolvido ao elemento de origem ao fechar,
 * Tab preso dentro do diálogo e Esc para fechar. Renderizado via Teleport no
 * body para não sofrer com overflow/stacking dos containers da página.
 */
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    size: { type: String, default: 'md' },
    dismissible: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const titleId = useId();
const panel = ref(null);
let previouslyFocused = null;

const widths = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl' };

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function close() {
    if (props.dismissible) {
        emit('close');
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        event.stopPropagation();
        close();

        return;
    }

    if (event.key !== 'Tab' || !panel.value) {
        return;
    }

    const focusable = [...panel.value.querySelectorAll(FOCUSABLE)];
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            previouslyFocused = document.activeElement;
            document.body.style.overflow = 'hidden';
            await nextTick();
            const target = panel.value?.querySelector('[autofocus]') ?? panel.value?.querySelector(FOCUSABLE);
            (target ?? panel.value)?.focus();
        } else {
            document.body.style.overflow = '';
            previouslyFocused?.focus?.();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4" @keydown="onKeydown">
            <div class="absolute inset-0 bg-slate-900/50" aria-hidden="true" @click="close" />
            <div
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
                tabindex="-1"
                class="relative flex max-h-[92vh] w-full flex-col rounded-t-2xl bg-white shadow-xl focus:outline-none sm:rounded-2xl"
                :class="widths[size]"
            >
                <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <h2 :id="titleId" class="text-base font-semibold text-slate-900">{{ title }}</h2>
                    <button
                        v-if="dismissible"
                        type="button"
                        class="-m-1 rounded-md p-1 text-slate-400 hover:text-slate-600 focus-visible:outline-2 focus-visible:outline-brand-600"
                        aria-label="Fechar"
                        @click="close"
                    >
                        <AppIcon name="close" />
                    </button>
                </header>
                <div class="overflow-y-auto px-5 py-4">
                    <slot />
                </div>
                <footer v-if="$slots.footer" class="flex flex-col-reverse gap-2 border-t border-slate-200 px-5 py-3 sm:flex-row sm:justify-end">
                    <slot name="footer" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>
