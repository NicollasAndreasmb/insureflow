<script setup>
import { claimsApi } from '@/api';
import DataTable from '@/components/data/DataTable.vue';
import SearchInput from '@/components/data/SearchInput.vue';
import TablePagination from '@/components/data/TablePagination.vue';
import SelectInput from '@/components/form/SelectInput.vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { usePaginatedList } from '@/composables/usePaginatedList';
import { useAuthStore } from '@/stores/auth';
import { useOptionsStore } from '@/stores/options';
import { formatDate, formatMoney } from '@/utils/format';

const auth = useAuthStore();
const options = useOptionsStore();

const list = usePaginatedList(claimsApi.list, {
    filterKeys: ['search', 'status', 'open', 'policy_id', 'client_id'],
    defaultSort: '-reported_on',
});

const openOptions = [{ value: '1', label: 'Somente em aberto' }];

const columns = [
    { key: 'protocol', label: 'Protocolo', sortable: true },
    { key: 'client', label: 'Cliente / apólice' },
    { key: 'occurred_on', label: 'Ocorrência', sortable: true, class: 'hidden md:table-cell' },
    { key: 'estimated_amount', label: 'Valor estimado', sortable: true, align: 'right', class: 'hidden sm:table-cell' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <div>
        <PageHeader title="Sinistros" subtitle="Ocorrências comunicadas pelos segurados e seu andamento.">
            <template #actions>
                <AppButton v-if="auth.can('claims.manage')" :to="{ name: 'claims.create' }" icon="plus">Abrir sinistro</AppButton>
            </template>
        </PageHeader>

        <section class="rounded-xl border border-slate-200 bg-white shadow-xs">
            <form class="grid grid-cols-1 gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-4" role="search" @submit.prevent>
                <SearchInput
                    v-model="list.filters.search"
                    class="sm:col-span-2"
                    label="Buscar sinistros"
                    placeholder="Protocolo, apólice ou cliente"
                    @update:model-value="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.status"
                    aria-label="Status"
                    placeholder="Todos os status"
                    :options="options.list('claim_statuses')"
                    @update:model-value="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.open"
                    aria-label="Andamento"
                    placeholder="Abertos e encerrados"
                    :options="openOptions"
                    @update:model-value="list.applyFilters()"
                />
            </form>
            <div
                v-if="list.filters.policy_id || list.filters.client_id"
                class="flex items-center justify-between gap-3 border-b border-slate-200 bg-brand-50/60 px-4 py-2 text-sm text-brand-900"
            >
                <span>Exibindo sinistros de {{ list.filters.policy_id ? 'uma apólice específica' : 'um cliente específico' }}.</span>
                <button type="button" class="font-medium hover:underline" @click="list.applyFilters({ policy_id: '', client_id: '' })">Remover filtro</button>
            </div>

            <ErrorState v-if="list.error" :error="list.error" @retry="list.reload" />
            <DataTable
                v-else
                :columns="columns"
                :rows="list.items"
                :loading="list.loading"
                :sort="list.sort"
                caption="Lista de sinistros"
                @sort="list.setSort"
            >
                <template #cell-protocol="{ row }">
                    <RouterLink :to="{ name: 'claims.show', params: { id: row.id } }" class="font-medium text-brand-800 tabular-nums hover:underline">
                        {{ row.protocol }}
                    </RouterLink>
                    <p class="text-xs text-slate-500">Comunicado em {{ formatDate(row.reported_on) }}</p>
                </template>
                <template #cell-client="{ row }">
                    <span class="block text-slate-800">{{ row.policy?.client?.name }}</span>
                    <RouterLink :to="{ name: 'policies.show', params: { id: row.policy_id } }" class="text-xs text-slate-500 hover:text-brand-700 hover:underline">
                        {{ row.policy?.number }} · {{ row.policy?.product?.name }}
                    </RouterLink>
                </template>
                <template #cell-occurred_on="{ row }">{{ formatDate(row.occurred_on) }}</template>
                <template #cell-estimated_amount="{ row }">{{ formatMoney(row.estimated_amount) }}</template>
                <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
                <template #empty>
                    <EmptyState
                        :title="list.hasActiveFilters ? 'Nenhum sinistro encontrado' : 'Nenhum sinistro registrado'"
                        :description="list.hasActiveFilters ? 'Ajuste ou limpe os filtros para ver mais resultados.' : ''"
                        icon="alert"
                    >
                        <AppButton v-if="list.hasActiveFilters" variant="secondary" @click="list.clearFilters()">Limpar filtros</AppButton>
                    </EmptyState>
                </template>
            </DataTable>
            <TablePagination :meta="list.meta" @change="list.setPage" />
        </section>
    </div>
</template>
