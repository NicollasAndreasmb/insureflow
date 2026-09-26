<script setup>
import { clientsApi } from '@/api';
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
import { useOptionsStore } from '@/stores/options';
import { formatCityState, formatDocument, formatNumber, formatPhone } from '@/utils/format';

const auth = useAuthStore();
const options = useOptionsStore();

const list = usePaginatedList(clientsApi.list, {
    filterKeys: ['search', 'person_type', 'is_active', 'state'],
    defaultSort: 'name',
});

const activeOptions = [
    { value: '1', label: 'Ativos' },
    { value: '0', label: 'Inativos' },
];
const stateOptions = options.list('states').map((state) => ({ value: state, label: state }));

const columns = [
    { key: 'name', label: 'Cliente', sortable: true },
    { key: 'person_type', label: 'Tipo', class: 'hidden md:table-cell' },
    { key: 'location', label: 'Cidade/UF', class: 'hidden lg:table-cell' },
    { key: 'contact', label: 'Contato', class: 'hidden xl:table-cell' },
    { key: 'policies_count', label: 'Apólices', align: 'right' },
    { key: 'is_active', label: 'Situação' },
];
</script>

<template>
    <div>
        <PageHeader title="Clientes" subtitle="Pessoas físicas e jurídicas atendidas pela corretora.">
            <template #actions>
                <AppButton v-if="auth.can('clients.create')" :to="{ name: 'clients.create' }" icon="plus">Novo cliente</AppButton>
            </template>
        </PageHeader>

        <section class="rounded-xl border border-slate-200 bg-white shadow-xs">
            <form class="grid grid-cols-1 gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-4" role="search" @submit.prevent>
                <SearchInput
                    v-model="list.filters.search"
                    class="sm:col-span-2 lg:col-span-1"
                    label="Buscar clientes"
                    placeholder="Nome, e-mail ou CPF/CNPJ"
                    @update:model-value="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.person_type"
                    aria-label="Tipo de pessoa"
                    placeholder="Todos os tipos"
                    :options="options.list('person_types')"
                    @update:model-value="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.is_active"
                    aria-label="Situação"
                    placeholder="Todas as situações"
                    :options="activeOptions"
                    @update:model-value="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.state"
                    aria-label="Estado"
                    placeholder="Todos os estados"
                    :options="stateOptions"
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
                caption="Lista de clientes"
                @sort="list.setSort"
            >
                <template #cell-name="{ row }">
                    <RouterLink :to="{ name: 'clients.show', params: { id: row.id } }" class="font-medium text-brand-800 hover:underline">
                        {{ row.name }}
                    </RouterLink>
                    <p class="text-xs text-slate-500 tabular-nums">{{ formatDocument(row.document) }}</p>
                </template>
                <template #cell-person_type="{ row }">{{ row.person_type.label }}</template>
                <template #cell-location="{ row }">
                    {{ formatCityState(row.city, row.state) }}
                </template>
                <template #cell-contact="{ row }">
                    <span class="block">{{ row.email ?? '—' }}</span>
                    <span class="text-xs text-slate-500">{{ formatPhone(row.mobile || row.phone) }}</span>
                </template>
                <template #cell-policies_count="{ row }">{{ formatNumber(row.policies_count) }}</template>
                <template #cell-is_active="{ row }"><ActiveBadge :active="row.is_active" /></template>
                <template #empty>
                    <EmptyState
                        :title="list.hasActiveFilters ? 'Nenhum cliente encontrado' : 'Nenhum cliente cadastrado'"
                        :description="list.hasActiveFilters ? 'Ajuste ou limpe os filtros para ver mais resultados.' : ''"
                        icon="users"
                    >
                        <AppButton v-if="list.hasActiveFilters" variant="secondary" @click="list.clearFilters()">Limpar filtros</AppButton>
                    </EmptyState>
                </template>
            </DataTable>
            <TablePagination :meta="list.meta" @change="list.setPage" />
        </section>
    </div>
</template>
