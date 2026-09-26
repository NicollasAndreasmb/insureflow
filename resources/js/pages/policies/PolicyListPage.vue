<script setup>
import { onMounted, ref } from 'vue';
import { policiesApi } from '@/api';
import DataTable from '@/components/data/DataTable.vue';
import SearchInput from '@/components/data/SearchInput.vue';
import TablePagination from '@/components/data/TablePagination.vue';
import SelectInput from '@/components/form/SelectInput.vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { fetchInsurerOptions, fetchProductOptions } from '@/composables/useLookups';
import { usePaginatedList } from '@/composables/usePaginatedList';
import { useAuthStore } from '@/stores/auth';
import { useOptionsStore } from '@/stores/options';
import { formatDate, formatMoney } from '@/utils/format';

const auth = useAuthStore();
const options = useOptionsStore();

const list = usePaginatedList(policiesApi.list, {
    filterKeys: ['search', 'status', 'insurer_id', 'insurance_product_id', 'expiring_within_days', 'client_id', 'broker_id'],
    defaultSort: '-starts_on',
});

const insurerOptions = ref([]);
const productOptions = ref([]);
const expiringOptions = [30, 60, 90].map((days) => ({ value: String(days), label: `Vencendo em até ${days} dias` }));

const columns = [
    { key: 'number', label: 'Apólice', sortable: true },
    { key: 'client', label: 'Cliente' },
    { key: 'product', label: 'Modalidade', class: 'hidden lg:table-cell' },
    { key: 'ends_on', label: 'Vigência', sortable: true, class: 'hidden md:table-cell' },
    { key: 'premium', label: 'Prêmio', sortable: true, align: 'right' },
    { key: 'status', label: 'Status' },
];

onMounted(async () => {
    [insurerOptions.value, productOptions.value] = await Promise.all([fetchInsurerOptions(), fetchProductOptions()]).catch(() => [[], []]);
});
</script>

<template>
    <div>
        <PageHeader title="Apólices" subtitle="Carteira de apólices da corretora.">
            <template #actions>
                <AppButton v-if="auth.can('policies.manage')" :to="{ name: 'policies.create' }" icon="plus">Nova apólice</AppButton>
            </template>
        </PageHeader>

        <section class="rounded-xl border border-slate-200 bg-white shadow-xs">
            <form class="grid grid-cols-1 gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 xl:grid-cols-5" role="search" @submit.prevent>
                <SearchInput
                    v-model="list.filters.search"
                    class="sm:col-span-2 xl:col-span-1"
                    label="Buscar apólices"
                    placeholder="Número ou cliente"
                    @update:model-value="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.status"
                    aria-label="Status"
                    placeholder="Todos os status"
                    :options="options.list('policy_statuses')"
                    @update:model-value="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.insurer_id"
                    aria-label="Seguradora"
                    placeholder="Todas as seguradoras"
                    :options="insurerOptions.map((option) => ({ ...option, value: String(option.value) }))"
                    @update:model-value="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.insurance_product_id"
                    aria-label="Modalidade"
                    placeholder="Todas as modalidades"
                    :options="productOptions.map((option) => ({ ...option, value: String(option.value) }))"
                    @update:model-value="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.expiring_within_days"
                    aria-label="Vencimento"
                    placeholder="Qualquer vencimento"
                    :options="expiringOptions"
                    @update:model-value="list.applyFilters()"
                />
            </form>
            <div
                v-if="list.filters.client_id || list.filters.broker_id"
                class="flex items-center justify-between gap-3 border-b border-slate-200 bg-brand-50/60 px-4 py-2 text-sm text-brand-900"
            >
                <span>Exibindo apólices de {{ list.filters.client_id ? 'um cliente específico' : 'um corretor específico' }}.</span>
                <button type="button" class="font-medium hover:underline" @click="list.applyFilters({ client_id: '', broker_id: '' })">Remover filtro</button>
            </div>

            <ErrorState v-if="list.error" :error="list.error" @retry="list.reload" />
            <DataTable
                v-else
                :columns="columns"
                :rows="list.items"
                :loading="list.loading"
                :sort="list.sort"
                caption="Lista de apólices"
                @sort="list.setSort"
            >
                <template #cell-number="{ row }">
                    <RouterLink :to="{ name: 'policies.show', params: { id: row.id } }" class="font-medium text-brand-800 tabular-nums hover:underline">
                        {{ row.number }}
                    </RouterLink>
                    <p class="text-xs text-slate-500">{{ row.insurer?.trade_name }}</p>
                </template>
                <template #cell-client="{ row }">
                    <RouterLink :to="{ name: 'clients.show', params: { id: row.client_id } }" class="text-slate-800 hover:text-brand-700 hover:underline">
                        {{ row.client?.name }}
                    </RouterLink>
                    <p class="text-xs text-slate-500 lg:hidden">{{ row.product?.name }}</p>
                </template>
                <template #cell-product="{ row }">{{ row.product?.name }}</template>
                <template #cell-ends_on="{ row }">
                    <span class="whitespace-nowrap">{{ formatDate(row.starts_on) }} a {{ formatDate(row.ends_on) }}</span>
                </template>
                <template #cell-premium="{ row }">{{ formatMoney(row.premium) }}</template>
                <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
                <template #empty>
                    <EmptyState
                        :title="list.hasActiveFilters ? 'Nenhuma apólice encontrada' : 'Nenhuma apólice cadastrada'"
                        :description="list.hasActiveFilters ? 'Ajuste ou limpe os filtros para ver mais resultados.' : ''"
                        icon="shield"
                    >
                        <AppButton v-if="list.hasActiveFilters" variant="secondary" @click="list.clearFilters()">Limpar filtros</AppButton>
                    </EmptyState>
                </template>
            </DataTable>
            <TablePagination :meta="list.meta" @change="list.setPage" />
        </section>
    </div>
</template>
