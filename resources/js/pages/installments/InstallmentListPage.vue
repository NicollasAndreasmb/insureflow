<script setup>
/*
 * Consulta financeira de parcelas de todas as apólices. O filtro de status
 * usa os status efetivos da API ("atrasado" e "pendente" são exclusivos),
 * e o pagamento reaproveita o mesmo modal da página da apólice.
 */
import { ref } from 'vue';
import { installmentsApi } from '@/api';
import TablePagination from '@/components/data/TablePagination.vue';
import SelectInput from '@/components/form/SelectInput.vue';
import TextInput from '@/components/form/TextInput.vue';
import InstallmentTable from '@/components/installments/InstallmentTable.vue';
import PayInstallmentModal from '@/components/installments/PayInstallmentModal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { usePaginatedList } from '@/composables/usePaginatedList';
import { useOptionsStore } from '@/stores/options';

const options = useOptionsStore();

const list = usePaginatedList(installmentsApi.list, {
    filterKeys: ['status', 'due_from', 'due_until', 'policy_id', 'client_id'],
    defaultSort: 'due_date',
});

const paying = ref(null);

function onPaid() {
    paying.value = null;
    list.reload();
}
</script>

<template>
    <div>
        <PageHeader title="Parcelas" subtitle="Acompanhamento financeiro das parcelas de todas as apólices." />

        <section class="rounded-xl border border-slate-200 bg-white shadow-xs">
            <form class="grid grid-cols-1 gap-3 border-b border-slate-200 p-4 sm:grid-cols-3" role="search" @submit.prevent="list.applyFilters()">
                <SelectInput
                    v-model="list.filters.status"
                    label="Situação"
                    placeholder="Todas"
                    :options="options.list('installment_statuses')"
                    @update:model-value="list.applyFilters()"
                />
                <TextInput v-model="list.filters.due_from" type="date" label="Vencimento a partir de" @change="list.applyFilters()" />
                <TextInput v-model="list.filters.due_until" type="date" label="Vencimento até" @change="list.applyFilters()" />
            </form>
            <div
                v-if="list.filters.policy_id || list.filters.client_id"
                class="flex items-center justify-between gap-3 border-b border-slate-200 bg-brand-50/60 px-4 py-2 text-sm text-brand-900"
            >
                <span>Exibindo parcelas de {{ list.filters.policy_id ? 'uma apólice específica' : 'um cliente específico' }}.</span>
                <button type="button" class="font-medium hover:underline" @click="list.applyFilters({ policy_id: '', client_id: '' })">Remover filtro</button>
            </div>

            <ErrorState v-if="list.error" :error="list.error" @retry="list.reload" />
            <InstallmentTable v-else show-policy :rows="list.items" :loading="list.loading" :sort="list.sort" @sort="list.setSort" @pay="paying = $event">
                <template #empty>
                    <EmptyState
                        :title="list.hasActiveFilters ? 'Nenhuma parcela encontrada' : 'Nenhuma parcela gerada'"
                        :description="list.hasActiveFilters ? 'Ajuste ou limpe os filtros para ver mais resultados.' : ''"
                        icon="cash"
                    >
                        <AppButton v-if="list.hasActiveFilters" variant="secondary" @click="list.clearFilters()">Limpar filtros</AppButton>
                    </EmptyState>
                </template>
            </InstallmentTable>
            <TablePagination :meta="list.meta" @change="list.setPage" />
        </section>

        <PayInstallmentModal :installment="paying" @close="paying = null" @paid="onPaid" />
    </div>
</template>
