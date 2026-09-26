<script setup>
/*
 * Tabela de listagem usada por todos os módulos.
 *
 * - Colunas: {key, label, sortable?, class?}. Células customizadas pelo slot
 *   `cell-<key>`; o texto padrão é row[key].
 * - Ordenação: apenas emite o novo valor de sort (`campo` / `-campo`); quem
 *   ordena é a API. O cabeçalho informa o estado com aria-sort.
 * - Telas pequenas: a tabela rola horizontalmente dentro do card, e colunas
 *   secundárias podem ser ocultadas com classes responsivas (ex.:
 *   "hidden md:table-cell") para manter as principais legíveis.
 * - Estados: carregamento inicial, recarga (tabela esmaecida, aria-busy) e
 *   lista vazia (slot `empty`).
 */
import AppIcon from '../ui/AppIcon.vue';
import EmptyState from '../ui/EmptyState.vue';
import LoadingState from '../ui/LoadingState.vue';

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    sort: { type: String, default: '' },
    rowKey: { type: String, default: 'id' },
    caption: { type: String, default: '' },
});

const emit = defineEmits(['sort']);

function sortState(column) {
    if (props.sort === column.key) {
        return 'ascending';
    }

    return props.sort === `-${column.key}` ? 'descending' : 'none';
}

function toggleSort(column) {
    emit('sort', props.sort === column.key ? `-${column.key}` : column.key);
}
</script>

<template>
    <div>
        <LoadingState v-if="loading && !rows.length" />
        <template v-else-if="!rows.length">
            <slot name="empty">
                <EmptyState />
            </slot>
        </template>
        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm" :aria-busy="loading || undefined">
                <caption v-if="caption" class="sr-only">{{ caption }}</caption>
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            :aria-sort="column.sortable ? sortState(column) : undefined"
                            class="px-4 py-3 text-left text-xs font-semibold tracking-wide whitespace-nowrap text-slate-500 uppercase"
                            :class="[column.class, column.align === 'right' ? 'text-right' : '']"
                        >
                            <button
                                v-if="column.sortable"
                                type="button"
                                class="inline-flex items-center gap-1 rounded uppercase hover:text-slate-800 focus-visible:outline-2 focus-visible:outline-brand-600"
                                @click="toggleSort(column)"
                            >
                                {{ column.label }}
                                <AppIcon
                                    :name="sortState(column) === 'ascending' ? 'arrowUp' : sortState(column) === 'descending' ? 'arrowDown' : 'selector'"
                                    :size="14"
                                    :class="sortState(column) === 'none' ? 'text-slate-300' : 'text-brand-700'"
                                />
                            </button>
                            <span v-else>{{ column.label }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white transition-opacity" :class="{ 'opacity-50': loading }">
                    <tr v-for="row in rows" :key="row[rowKey]" class="hover:bg-slate-50/70">
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 align-middle text-slate-700"
                            :class="[column.class, column.align === 'right' ? 'text-right tabular-nums' : '']"
                        >
                            <slot :name="`cell-${column.key}`" :row="row">
                                {{ row[column.key] ?? '—' }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
