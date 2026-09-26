<script setup>
import AppSpinner from './AppSpinner.vue';

defineProps({
    label: { type: String, default: 'Carregando…' },
});
</script>

<template>
    <div role="status" class="flex items-center justify-center gap-2 py-12 text-sm text-slate-500">
        <AppSpinner :size="18" />
        <span>{{ label }}</span>
    </div>
</template>
