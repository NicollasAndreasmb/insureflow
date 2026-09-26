<script setup>
/*
 * Consulta da trilha de auditoria (/api/activity-logs). A visibilidade dos
 * registros é decidida pela API (ex.: auditoria de usuários só para
 * administradores); nomes de entidades, ações e campos vêm de /api/options.
 */
import { activityLogsApi } from '@/api';
import ActivityChanges from '@/components/activity/ActivityChanges.vue';
import DataTable from '@/components/data/DataTable.vue';
import TablePagination from '@/components/data/TablePagination.vue';
import SelectInput from '@/components/form/SelectInput.vue';
import TextInput from '@/components/form/TextInput.vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { usePaginatedList } from '@/composables/usePaginatedList';
import { useOptionsStore } from '@/stores/options';
import { formatDateTime } from '@/utils/format';

const options = useOptionsStore();

const list = usePaginatedList(activityLogsApi.list, {
    filterKeys: ['subject_type', 'subject_id', 'action'],
    defaultSort: '-created_at',
    perPage: 25,
});

const DETAIL_ROUTES = { client: 'clients', insurer: 'insurers', policy: 'policies', claim: 'claims' };

function subjectLink(log) {
    const base = DETAIL_ROUTES[log.subject_type];

    return base ? `/${base}/${log.subject_id}` : null;
}

const columns = [
    { key: 'created_at', label: 'Data/hora', sortable: true },
    { key: 'user', label: 'Usuário', class: 'hidden md:table-cell' },
    { key: 'action', label: 'Ação' },
    { key: 'subject', label: 'Registro' },
    { key: 'changes', label: 'Alterações', class: 'hidden lg:table-cell' },
];
</script>

<template>
    <div>
        <PageHeader title="Auditoria" subtitle="Registro das operações realizadas no sistema." />

        <section class="rounded-xl border border-slate-200 bg-white shadow-xs">
            <form class="grid grid-cols-1 gap-3 border-b border-slate-200 p-4 sm:grid-cols-3" role="search" @submit.prevent="list.applyFilters()">
                <SelectInput
                    v-model="list.filters.subject_type"
                    label="Entidade"
                    placeholder="Todas"
                    :options="options.list('activity_subject_types')"
                    @update:model-value="list.applyFilters({ ...list.filters, subject_id: '' })"
                />
                <TextInput
                    v-model="list.filters.subject_id"
                    type="number"
                    min="1"
                    label="Código do registro"
                    :disabled="!list.filters.subject_type"
                    @change="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.action"
                    label="Ação"
                    placeholder="Todas"
                    :options="options.list('activity_actions')"
                    @update:model-value="list.applyFilters()"
                />
            </form>

            <ErrorState v-if="list.error" :error="list.error" @retry="list.reload" />
            <DataTable
                v-else
                :columns="columns"
                :rows="list.items"
                :loading="list.loading"
                :sort="list.sort"
                caption="Registros de auditoria"
                @sort="list.setSort"
            >
                <template #cell-created_at="{ row }">
                    <time :datetime="row.created_at" class="whitespace-nowrap tabular-nums">{{ formatDateTime(row.created_at) }}</time>
                </template>
                <template #cell-user="{ row }">{{ row.user?.name ?? 'Sistema' }}</template>
                <template #cell-action="{ row }">
                    <span class="font-medium text-slate-800">{{ row.action.label }}</span>
                    <p class="text-xs text-slate-500 md:hidden">{{ row.user?.name ?? 'Sistema' }}</p>
                </template>
                <template #cell-subject="{ row }">
                    <RouterLink v-if="subjectLink(row)" :to="subjectLink(row)" class="text-brand-800 hover:underline">
                        {{ options.label('activity_subject_types', row.subject_type) }} #{{ row.subject_id }}
                    </RouterLink>
                    <span v-else>{{ options.label('activity_subject_types', row.subject_type) }} #{{ row.subject_id }}</span>
                </template>
                <template #cell-changes="{ row }">
                    <ActivityChanges :log="row" />
                </template>
                <template #empty>
                    <EmptyState title="Nenhum registro de auditoria" icon="clipboard">
                        <AppButton v-if="list.hasActiveFilters" variant="secondary" @click="list.clearFilters()">Limpar filtros</AppButton>
                    </EmptyState>
                </template>
            </DataTable>
            <TablePagination :meta="list.meta" @change="list.setPage" />
        </section>
    </div>
</template>
