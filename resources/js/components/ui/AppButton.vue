<script setup>
/*
 * Botão padrão. Com `loading` fica desabilitado e exibe um spinner, impedindo
 * envio duplicado enquanto a requisição está em andamento. Com `to`, renderiza
 * um RouterLink com a mesma aparência.
 */
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';
import AppSpinner from './AppSpinner.vue';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    type: { type: String, default: 'button' },
    to: { type: [String, Object], default: null },
    icon: { type: String, default: null },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const variants = {
    primary: 'bg-brand-900 text-white hover:bg-brand-800 focus-visible:outline-brand-600',
    secondary: 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus-visible:outline-brand-600',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-600',
    'danger-ghost': 'text-red-700 hover:bg-red-50 focus-visible:outline-red-600',
    ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-brand-600',
};

const sizes = {
    sm: 'gap-1.5 rounded-md px-2.5 py-1.5 text-sm',
    md: 'gap-2 rounded-lg px-3.5 py-2 text-sm',
};

const classes = computed(() => [
    'inline-flex items-center justify-center font-medium whitespace-nowrap transition-colors',
    'focus-visible:outline-2 focus-visible:outline-offset-2',
    'disabled:cursor-not-allowed disabled:opacity-60',
    variants[props.variant],
    sizes[props.size],
]);
</script>

<template>
    <RouterLink v-if="to" :to="to" :class="classes">
        <AppIcon v-if="icon" :name="icon" :size="16" />
        <slot />
    </RouterLink>
    <button
        v-else
        :type="type"
        :class="classes"
        :disabled="disabled || loading"
        :aria-busy="loading || undefined"
    >
        <AppSpinner v-if="loading" />
        <AppIcon v-else-if="icon" :name="icon" :size="16" />
        <slot />
    </button>
</template>
