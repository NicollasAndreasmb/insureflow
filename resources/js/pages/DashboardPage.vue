<script setup>
/*
 * Dashboard: apresenta os agregados de /api/dashboard sem recalculá-los.
 *
 * Os cards levam às listagens já filtradas (ex.: parcelas atrasadas), e a
 * janela de "próximos vencimentos" é enviada à API em expiring_within_days.
 * Há um único gráfico (apólices por modalidade), onde a comparação visual
 * realmente ajuda; os demais blocos são listas e números.
 */
import { computed, ref } from 'vue';
import { fetchDashboard } from '@/api';
import BarChart from '@/components/dashboard/BarChart.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import AppCard from '@/components/ui/AppCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import SelectInput from '@/components/form/SelectInput.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useAsyncData } from '@/composables/useAsyncData';
import { useAuthStore } from '@/stores/auth';
import { formatDate, formatDateTime, formatMoney, formatNumber } from '@/utils/format';

const auth = useAuthStore();
const windowDays = ref(30);
const windowOptions = [30, 60, 90].map((days) => ({ value: days, label: `Próximos ${days} dias` }));

const { data, loading, error, reload } = useAsyncData(
    (days) => fetchDashboard({ expiring_within_days: days }),
    () => windowDays.value,
);

const installmentRows = computed(() => {
    if (!data.value) {
        return [];
    }

    const { overdue, pending, paid, cancelled } = data.value.installments;
    const rows = [
        { key: 'overdue', ...overdue, bar: 'bg-amber-500' },
        { key: 'pending', ...pending, bar: 'bg-brand-500' },
        { key: 'paid', ...paid, bar: 'bg-accent-500' },
        { key: 'cancelled', ...cancelled, bar: 'bg-slate-300' },
    ];
    const max = Math.max(...rows.map((row) => Number(row.amount)), 1);

    return rows.map((row) => ({ ...row, width: `${(Number(row.amount) / max) * 100}%` }));
});

const productChart = computed(() => {
    const products = data.value?.policies_by_product ?? [];

    return {
        labels: products.map((product) => product.name),
        datasets: [
            { label: 'Ativas', data: products.map((product) => product.active_count), backgroundColor: '#14b89a', borderRadius: 4 },
            {
                label: 'Demais status',
                data: products.map((product) => product.policies_count - product.active_count),
                backgroundColor: '#bfd4fe',
                borderRadius: 4,
            },
        ],
    };
});

function daysLeftLabel(days) {
    if (days === 0) {
        return 'Vence hoje';
    }

    return days === 1 ? 'Vence amanhã' : `Em ${days} dias`;
}
</script>

<template>
    <div>
        <PageHeader title="Dashboard" :subtitle="`Olá, ${auth.user?.name}. Este é o panorama atual da carteira.`">
            <template #actions>
                <SelectInput v-model.number="windowDays" :options="windowOptions" aria-label="Janela de vencimentos" class="w-48" />
            </template>
        </PageHeader>

        <ErrorState v-if="error" :error="error" @retry="reload" />
        <LoadingState v-else-if="loading && !data" label="Carregando indicadores…" />

        <div v-else-if="data" class="space-y-6" :class="{ 'opacity-60 transition-opacity': loading }" :aria-busy="loading || undefined">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <StatCard
                    label="Clientes ativos"
                    :value="formatNumber(data.clients.active)"
                    :detail="`${formatNumber(data.clients.total)} cadastrados`"
                    icon="users"
                    :to="{ path: '/clients', query: { is_active: 1 } }"
                />
                <StatCard
                    label="Apólices ativas"
                    :value="formatNumber(data.policies.active)"
                    :detail="`${formatNumber(data.policies.total)} apólices no total`"
                    icon="shield"
                    tone="accent"
                    :to="{ path: '/policies', query: { status: 'active' } }"
                />
                <StatCard
                    label="Prêmio da carteira ativa"
                    :value="formatMoney(data.premiums.active_total)"
                    :detail="`Comissão: ${formatMoney(data.premiums.active_commission_total)}`"
                    icon="cash"
                    tone="accent"
                />
                <StatCard
                    :label="`Vencendo em ${data.expiring_window_days} dias`"
                    :value="formatNumber(data.policies.expiring_soon)"
                    detail="Apólices ativas a renovar"
                    icon="clock"
                    tone="warning"
                    :to="{ path: '/policies', query: { status: 'active', expiring_within_days: data.expiring_window_days, sort: 'ends_on' } }"
                />
                <StatCard
                    label="Parcelas em atraso"
                    :value="formatNumber(data.installments.overdue.count)"
                    :detail="formatMoney(data.installments.overdue.amount)"
                    icon="alert"
                    :tone="data.installments.overdue.count > 0 ? 'danger' : 'brand'"
                    :to="{ path: '/installments', query: { status: 'overdue' } }"
                />
                <StatCard
                    label="Sinistros em aberto"
                    :value="formatNumber(data.claims.open)"
                    :detail="`${formatNumber(data.claims.total)} sinistros registrados`"
                    icon="clipboard"
                    tone="warning"
                    :to="{ path: '/claims', query: { open: 1 } }"
                />
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <AppCard
                    class="xl:col-span-2"
                    title="Próximos vencimentos"
                    :description="`Apólices ativas que terminam nos próximos ${data.expiring_window_days} dias`"
                    :padded="false"
                >
                    <EmptyState
                        v-if="!data.upcoming_expirations.length"
                        title="Nenhum vencimento no período"
                        description="Não há apólices ativas terminando nesta janela."
                        icon="check"
                    />
                    <ul v-else class="divide-y divide-slate-100">
                        <li v-for="policy in data.upcoming_expirations" :key="policy.id">
                            <RouterLink
                                :to="`/policies/${policy.id}`"
                                class="flex items-center justify-between gap-4 px-5 py-3 hover:bg-slate-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand-600"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ policy.client.name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ policy.number }} · {{ policy.product.name }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-medium" :class="policy.days_left <= 7 ? 'text-red-700' : 'text-slate-700'">
                                        {{ daysLeftLabel(policy.days_left) }}
                                    </p>
                                    <p class="text-xs text-slate-500">{{ formatDate(policy.ends_on) }}</p>
                                </div>
                            </RouterLink>
                        </li>
                    </ul>
                </AppCard>

                <AppCard title="Situação financeira" description="Parcelas por situação">
                    <ul class="space-y-4">
                        <li v-for="row in installmentRows" :key="row.key">
                            <div class="flex items-baseline justify-between gap-2 text-sm">
                                <RouterLink
                                    :to="{ path: '/installments', query: { status: row.key } }"
                                    class="font-medium text-slate-700 hover:text-brand-700 hover:underline"
                                >
                                    {{ row.label }}
                                </RouterLink>
                                <span class="text-slate-500 tabular-nums">{{ formatNumber(row.count) }}</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                <div class="h-full rounded-full" :class="row.bar" :style="{ width: row.width }" />
                            </div>
                            <p class="mt-1 text-xs text-slate-500 tabular-nums">{{ formatMoney(row.amount) }}</p>
                        </li>
                    </ul>
                </AppCard>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <AppCard class="xl:col-span-2" title="Apólices por modalidade">
                    <EmptyState v-if="!data.policies.total" title="Nenhuma apólice cadastrada" icon="shield" />
                    <BarChart
                        v-else
                        :labels="productChart.labels"
                        :datasets="productChart.datasets"
                        summary="Quantidade de apólices por modalidade, separando as ativas das demais"
                    />
                </AppCard>

                <AppCard title="Status da carteira">
                    <h3 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Apólices</h3>
                    <ul class="mt-2 mb-5 space-y-1.5">
                        <li v-for="status in data.policies.by_status" :key="status.value" class="flex items-center justify-between text-sm">
                            <StatusBadge :status="status" />
                            <span class="text-slate-700 tabular-nums">{{ formatNumber(status.count) }}</span>
                        </li>
                    </ul>
                    <h3 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Sinistros</h3>
                    <ul class="mt-2 space-y-1.5">
                        <li v-for="status in data.claims.by_status" :key="status.value" class="flex items-center justify-between text-sm">
                            <StatusBadge :status="status" />
                            <span class="text-slate-700 tabular-nums">{{ formatNumber(status.count) }}</span>
                        </li>
                    </ul>
                </AppCard>
            </div>

            <p class="text-right text-xs text-slate-500">Atualizado em {{ formatDateTime(data.generated_at) }}</p>
        </div>
    </div>
</template>
