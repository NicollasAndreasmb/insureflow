<script setup>
/*
 * Lista de pares rótulo/valor para telas de detalhe. Valores vazios aparecem
 * como "—". Um item pode ser customizado com o slot `item-<key>`.
 */
defineProps({
    items: { type: Array, required: true },
    columns: { type: Number, default: 2 },
});
</script>

<template>
    <dl class="grid grid-cols-1 gap-x-6 gap-y-4" :class="{ 'sm:grid-cols-2': columns >= 2, 'lg:grid-cols-3': columns >= 3 }">
        <div v-for="item in items" :key="item.key" :class="item.wide ? 'sm:col-span-full' : ''">
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">{{ item.label }}</dt>
            <dd class="mt-1 text-sm break-words text-slate-900">
                <slot :name="`item-${item.key}`" :item="item">
                    {{ item.value === null || item.value === undefined || item.value === '' ? '—' : item.value }}
                </slot>
            </dd>
        </div>
    </dl>
</template>
