<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <header class="mb-6">
        <nav v-if="breadcrumbs.length" aria-label="Trilha de navegação" class="mb-2">
            <ol class="flex flex-wrap items-center gap-1 text-sm text-slate-500">
                <li v-for="(crumb, index) in breadcrumbs" :key="index" class="flex items-center gap-1">
                    <AppIcon v-if="index > 0" name="chevronRight" :size="14" class="text-slate-400" />
                    <RouterLink v-if="crumb.to" :to="crumb.to" class="rounded hover:text-brand-700 hover:underline focus-visible:outline-2 focus-visible:outline-brand-600">
                        {{ crumb.label }}
                    </RouterLink>
                    <span v-else aria-current="page" class="text-slate-700">{{ crumb.label }}</span>
                </li>
            </ol>
        </nav>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 tabindex="-1" data-page-title class="truncate text-2xl font-semibold text-brand-950 focus:outline-none">{{ title }}</h1>
                <p v-if="subtitle || $slots.subtitle" class="mt-1 text-sm text-slate-500">
                    <slot name="subtitle">{{ subtitle }}</slot>
                </p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap gap-2">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
