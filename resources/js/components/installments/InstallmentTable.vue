<script setup>
/*
 * Tabela de parcelas usada na apólice e na consulta financeira. O status
 * exibido é o efetivo calculado pela API (inclui "Atrasado"), e o botão de
 * pagamento aparece conforme `is_payable` da parcela e a permissão
 * installments.pay do usuário — nenhuma regra de atraso é recalculada aqui.
 */
import { computed } from 'vue';
import DataTable from '../data/DataTable.vue';
import AppButton from '../ui/AppButton.vue';
import EmptyState from '../ui/EmptyState.vue';
import StatusBadge from '../ui/StatusBadge.vue';
import { useAuthStore } from '@/stores/auth';
import { formatDate, formatMoney } from '@/utils/format';

const props = defineProps({
    rows: { type: Array, required: true },
    loading: { type: Boolean, default: false },
    showPolicy: { type: Boolean, default: false },
    sort: { type: String, default: '' },
});

defineEmits(['pay', 'sort']);

const auth = useAuthStore();

const columns = computed(() => [
    ...(props.showPolicy ? [{ key: 'policy', label: 'Apólice / cliente' }] : []),
    { key: 'number', label: 'Parcela' },
    { key: 'due_date', label: 'Vencimento', sortable: props.showPolicy },
    { key: 'amount', label: 'Valor', align: 'right', sortable: props.showPolicy },
    { key: 'status', label: 'Status' },
    { key: 'paid_on', label: 'Pagamento', class: 'hidden sm:table-cell', sortable: props.showPolicy },
    ...(auth.can('installments.pay') ? [{ key: 'actions', label: 'Ações', align: 'right' }] : []),
]);
</script>

<template>
    <DataTable :columns="columns" :rows="rows" :loading="loading" :sort="sort" caption="Parcelas" @sort="$emit('sort', $event)">
        <template #cell-policy="{ row }">
            <RouterLink :to="`/policies/${row.policy_id}`" class="font-medium text-brand-800 hover:underline">
                {{ row.policy?.number }}
            </RouterLink>
            <p class="text-xs text-slate-500">{{ row.policy?.client?.name }}</p>
        </template>
        <template #cell-number="{ row }">{{ row.number }}ª</template>
        <template #cell-due_date="{ row }">
            <span :class="row.is_overdue ? 'font-medium text-red-700' : ''">{{ formatDate(row.due_date) }}</span>
        </template>
        <template #cell-amount="{ row }">{{ formatMoney(row.amount) }}</template>
        <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
        <template #cell-paid_on="{ row }">{{ formatDate(row.paid_on) }}</template>
        <template #cell-actions="{ row }">
            <AppButton v-if="row.is_payable" size="sm" variant="secondary" icon="check" @click="$emit('pay', row)">
                Registrar pagamento
            </AppButton>
        </template>
        <template #empty>
            <slot name="empty">
                <EmptyState title="Nenhuma parcela" icon="cash" />
            </slot>
        </template>
    </DataTable>
</template>
