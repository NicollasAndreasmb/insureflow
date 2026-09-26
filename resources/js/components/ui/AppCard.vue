<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <section class="rounded-xl border border-slate-200 bg-white shadow-xs">
        <header v-if="title || $slots.actions" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-3.5">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">{{ title }}</h2>
                <p v-if="description" class="text-xs text-slate-500">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap gap-2">
                <slot name="actions" />
            </div>
        </header>
        <div :class="padded ? 'p-5' : ''">
            <slot />
        </div>
    </section>
</template>
