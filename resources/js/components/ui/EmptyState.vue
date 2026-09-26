<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    title: { type: String, default: 'Nenhum registro encontrado' },
    description: { type: String, default: '' },
    icon: { type: String, default: 'document' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-4 py-10 text-center">
        <div class="mb-3 rounded-full bg-slate-100 p-3 text-slate-400">
            <AppIcon :name="icon" :size="24" />
        </div>
        <p class="font-medium text-slate-700">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-slate-500">{{ description }}</p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
