<script setup>
defineProps({
    active: { type: Boolean, required: true },
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
        :class="active ? 'bg-accent-50 text-accent-700 ring-accent-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'"
    >
        <span class="size-1.5 rounded-full" :class="active ? 'bg-accent-500' : 'bg-slate-400'" aria-hidden="true" />
        {{ active ? 'Ativo' : 'Inativo' }}
    </span>
</template>
