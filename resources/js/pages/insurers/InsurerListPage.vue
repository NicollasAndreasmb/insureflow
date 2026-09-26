<script setup>
import { insurersApi } from '@/api';
import DataTable from '@/components/data/DataTable.vue';
import SearchInput from '@/components/data/SearchInput.vue';
import TablePagination from '@/components/data/TablePagination.vue';
import SelectInput from '@/components/form/SelectInput.vue';
import ActiveBadge from '@/components/ui/ActiveBadge.vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { usePaginatedList } from '@/composables/usePaginatedList';
import { useAuthStore } from '@/stores/auth';
import { formatDocument, formatNumber, formatPhone } from '@/utils/format';

const auth = useAuthStore();

const list = usePaginatedList(insurersApi.list, {
    filterKeys: ['search', 'is_active'],
    defaultSort: 'trade_name',
});

const activeOptions = [
    { value: '1', label: 'Ativas' },
    { value: '0', label: 'Inativas' },
];

const columns = [
    { key: 'trade_name', label: 'Seguradora', sortable: true },
    { key: 'cnpj', label: 'CNPJ', class: 'hidden md:table-cell' },
    { key: 'contact', label: 'Contato', class: 'hidden lg:table-cell' },
    { key: 'policies_count', label: 'Apólices', align: 'right' },
    { key: 'is_active', label: 'Situação' },
];
</script>

<template>
    <div>
        <PageHeader title="Seguradoras" subtitle="Companhias com as quais a corretora opera.">
            <template #actions>
                <AppButton v-if="auth.can('insurers.manage')" :to="{ name: 'insurers.create' }" icon="plus">Nova seguradora</AppButton>
            </template>
        </PageHeader>

        <section class="rounded-xl border border-slate-200 bg-white shadow-xs">
            <form class="grid grid-cols-1 gap-3 border-b border-slate-200 p-4 sm:grid-cols-3" role="search" @submit.prevent>
                <SearchInput
                    v-model="list.filters.search"
                    class="sm:col-span-2"
                    label="Buscar seguradoras"
                    placeholder="Nome fantasia, razão social ou CNPJ"
                    @update:model-value="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.is_active"
                    aria-label="Situação"
                    placeholder="Todas as situações"
                    :options="activeOptions"
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
                caption="Lista de seguradoras"
                @sort="list.setSort"
            >
                <template #cell-trade_name="{ row }">
                    <RouterLink :to="{ name: 'insurers.show', params: { id: row.id } }" class="font-medium text-brand-800 hover:underline">
                        {{ row.trade_name }}
                    </RouterLink>
                    <p class="text-xs text-slate-500">{{ row.legal_name }}</p>
                </template>
                <template #cell-cnpj="{ row }"><span class="tabular-nums">{{ formatDocument(row.cnpj) }}</span></template>
                <template #cell-contact="{ row }">
                    <span class="block">{{ row.email ?? '—' }}</span>
                    <span class="text-xs text-slate-500">{{ formatPhone(row.phone) }}</span>
                </template>
                <template #cell-policies_count="{ row }">{{ formatNumber(row.policies_count) }}</template>
                <template #cell-is_active="{ row }"><ActiveBadge :active="row.is_active" /></template>
                <template #empty>
                    <EmptyState
                        :title="list.hasActiveFilters ? 'Nenhuma seguradora encontrada' : 'Nenhuma seguradora cadastrada'"
                        icon="building"
                    >
                        <AppButton v-if="list.hasActiveFilters" variant="secondary" @click="list.clearFilters()">Limpar filtros</AppButton>
                    </EmptyState>
                </template>
            </DataTable>
            <TablePagination :meta="list.meta" @change="list.setPage" />
        </section>
    </div>
</template>
