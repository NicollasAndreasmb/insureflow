<script setup>
/*
 * Visão central da apólice: partes, vigência e valores, transições de status,
 * coberturas, parcelas (com registro de pagamento), sinistros e histórico.
 *
 * O que pode ser feito vem da própria resposta da API (allowed_transitions,
 * is_editable, is_deletable, is_payable) combinado com as permissões do
 * usuário; após qualquer ação, a apólice é recarregada para refletir os
 * efeitos calculados no backend (parcelas geradas/canceladas, comissão).
 */
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { policiesApi } from '@/api';
import ActivityTimeline from '@/components/activity/ActivityTimeline.vue';
import DataTable from '@/components/data/DataTable.vue';
import FormAlert from '@/components/form/FormAlert.vue';
import TextareaInput from '@/components/form/TextareaInput.vue';
import InstallmentTable from '@/components/installments/InstallmentTable.vue';
import PayInstallmentModal from '@/components/installments/PayInstallmentModal.vue';
import CoverageSection from '@/components/policies/CoverageSection.vue';
import PolicyStatusActions from '@/components/policies/PolicyStatusActions.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppCard from '@/components/ui/AppCard.vue';
import AppModal from '@/components/ui/AppModal.vue';
import DetailList from '@/components/ui/DetailList.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useAsyncData } from '@/composables/useAsyncData';
import { useForm } from '@/composables/useForm';
import { useAuthStore } from '@/stores/auth';
import { useConfirmStore } from '@/stores/confirm';
import { useNotificationsStore } from '@/stores/notifications';
import { formatDate, formatMoney, formatPercent } from '@/utils/format';
import { maxLength } from '@/utils/validation';

const props = defineProps({
    id: { type: String, required: true },
});

const auth = useAuthStore();
const confirm = useConfirmStore();
const notifications = useNotificationsStore();
const router = useRouter();

const { data: policy, loading, error, reload } = useAsyncData((id) => policiesApi.get(id), () => props.id);

const history = ref(null);
const payingInstallment = ref(null);
const deleting = ref(false);
const notesOpen = ref(false);
const notesForm = useForm({ notes: '' }, { rules: { notes: [maxLength(5000)] } });

async function refresh() {
    await reload();
    history.value?.reload();
}

const parties = computed(() => {
    const p = policy.value;

    return [
        { key: 'client', label: 'Cliente', value: p.client?.name },
        { key: 'insurer', label: 'Seguradora', value: p.insurer?.trade_name },
        { key: 'product', label: 'Modalidade', value: p.product?.name },
        { key: 'broker', label: 'Corretor responsável', value: p.broker?.name },
        { key: 'term', label: 'Vigência', value: `${formatDate(p.starts_on)} a ${formatDate(p.ends_on)}` },
        { key: 'payment', label: 'Pagamento', value: `${p.payment_method.label} · ${p.installments_count === 1 ? 'à vista' : `${p.installments_count} parcelas`}` },
    ];
});

const values = computed(() => {
    const p = policy.value;

    return [
        { key: 'premium', label: 'Prêmio total', value: formatMoney(p.premium) },
        { key: 'deductible', label: 'Franquia', value: formatMoney(p.deductible) },
        { key: 'commission_rate', label: 'Taxa de comissão', value: formatPercent(p.commission_rate) },
        { key: 'commission_amount', label: 'Comissão', value: formatMoney(p.commission_amount) },
    ];
});

const claimColumns = [
    { key: 'protocol', label: 'Protocolo' },
    { key: 'occurred_on', label: 'Ocorrência', class: 'hidden sm:table-cell' },
    { key: 'estimated_amount', label: 'Valor estimado', align: 'right' },
    { key: 'status', label: 'Status' },
];

function onPaid() {
    payingInstallment.value = null;
    refresh();
}

function openNotes() {
    notesForm.reset({ notes: policy.value.notes ?? '' });
    notesOpen.value = true;
}

async function saveNotes() {
    const saved = await notesForm.submit((fields) => policiesApi.update(props.id, { notes: fields.notes || null }, 'patch'));

    if (saved) {
        notesOpen.value = false;
        notifications.success('Observações atualizadas.');
        refresh();
    }
}

async function remove() {
    const confirmed = await confirm.ask({
        title: 'Excluir cotação',
        message: `Excluir a cotação ${policy.value.number}? Esta ação remove a cotação da carteira.`,
        confirmLabel: 'Excluir',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    deleting.value = true;

    try {
        await policiesApi.remove(props.id);
        notifications.success('Cotação excluída.');
        router.push({ name: 'policies.index' });
    } catch (exception) {
        notifications.error(exception.message);
    } finally {
        deleting.value = false;
    }
}
</script>

<template>
    <div>
        <ErrorState v-if="error" :error="error" :retryable="!error.isNotFound" @retry="reload" />
        <LoadingState v-else-if="loading && !policy" />

        <template v-else-if="policy">
            <PageHeader
                :title="`Apólice ${policy.number}`"
                :breadcrumbs="[{ label: 'Apólices', to: { name: 'policies.index' } }, { label: policy.number }]"
            >
                <template #subtitle>
                    <span class="inline-flex flex-wrap items-center gap-2">
                        <StatusBadge :status="policy.status" />
                        <RouterLink :to="{ name: 'clients.show', params: { id: policy.client_id } }" class="hover:text-brand-700 hover:underline">
                            {{ policy.client?.name }}
                        </RouterLink>
                        <span>· {{ policy.product?.name }}</span>
                    </span>
                </template>
                <template #actions>
                    <AppButton v-if="auth.can('policies.delete') && policy.is_deletable" variant="danger-ghost" icon="trash" :loading="deleting" @click="remove">
                        Excluir
                    </AppButton>
                    <AppButton v-if="auth.can('claims.manage')" variant="secondary" icon="alert" :to="{ path: '/claims/new', query: { policy_id: policy.id } }">
                        Abrir sinistro
                    </AppButton>
                    <AppButton
                        v-if="auth.can('policies.manage') && policy.is_editable"
                        variant="secondary"
                        icon="pencil"
                        :to="{ name: 'policies.edit', params: { id } }"
                    >
                        Editar
                    </AppButton>
                </template>
            </PageHeader>

            <div v-if="auth.can('policies.manage') && policy.allowed_transitions.length" class="mb-6 rounded-xl border border-slate-200 bg-white px-5 py-3 shadow-xs">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-600">Situação: <strong class="text-slate-900">{{ policy.status.label }}</strong></p>
                    <PolicyStatusActions :policy="policy" @changed="refresh" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <AppCard title="Dados da apólice">
                        <DetailList :items="parties" :columns="3">
                            <template #item-client="{ item }">
                                <RouterLink :to="{ name: 'clients.show', params: { id: policy.client_id } }" class="text-brand-700 hover:underline">
                                    {{ item.value }}
                                </RouterLink>
                            </template>
                            <template #item-insurer="{ item }">
                                <RouterLink :to="{ name: 'insurers.show', params: { id: policy.insurer_id } }" class="text-brand-700 hover:underline">
                                    {{ item.value }}
                                </RouterLink>
                            </template>
                        </DetailList>
                    </AppCard>

                    <AppCard title="Valores">
                        <DetailList :items="values" :columns="3" />
                    </AppCard>

                    <CoverageSection :policy="policy" @changed="refresh" />

                    <AppCard title="Parcelas" :padded="false">
                        <InstallmentTable :rows="policy.installments" @pay="payingInstallment = $event">
                            <template #empty>
                                <EmptyState
                                    title="Nenhuma parcela"
                                    :description="policy.status.value === 'quote' ? 'As parcelas são geradas quando a cotação é emitida.' : ''"
                                    icon="cash"
                                />
                            </template>
                        </InstallmentTable>
                    </AppCard>

                    <AppCard title="Sinistros" :padded="false">
                        <DataTable :columns="claimColumns" :rows="policy.claims" caption="Sinistros da apólice">
                            <template #cell-protocol="{ row }">
                                <RouterLink :to="`/claims/${row.id}`" class="font-medium text-brand-800 tabular-nums hover:underline">{{ row.protocol }}</RouterLink>
                            </template>
                            <template #cell-occurred_on="{ row }">{{ formatDate(row.occurred_on) }}</template>
                            <template #cell-estimated_amount="{ row }">{{ formatMoney(row.estimated_amount) }}</template>
                            <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
                            <template #empty>
                                <EmptyState title="Nenhum sinistro registrado" icon="alert" />
                            </template>
                        </DataTable>
                    </AppCard>
                </div>

                <div class="space-y-6">
                    <AppCard title="Observações">
                        <template v-if="auth.can('policies.manage')" #actions>
                            <AppButton size="sm" variant="ghost" icon="pencil" @click="openNotes">Editar</AppButton>
                        </template>
                        <p class="text-sm whitespace-pre-line text-slate-700">{{ policy.notes || 'Sem observações.' }}</p>
                    </AppCard>
                    <AppCard title="Histórico">
                        <ActivityTimeline ref="history" subject-type="policy" :subject-id="policy.id" />
                    </AppCard>
                </div>
            </div>

            <PayInstallmentModal :installment="payingInstallment" @close="payingInstallment = null" @paid="onPaid" />

            <AppModal :open="notesOpen" title="Observações da apólice" @close="notesOpen = false">
                <form id="policy-notes-form" class="space-y-4" novalidate @submit.prevent="saveNotes">
                    <FormAlert :message="notesForm.generalError" />
                    <TextareaInput v-model="notesForm.fields.notes" label="Observações" :rows="5" autofocus :error="notesForm.error('notes')" />
                </form>
                <template #footer>
                    <AppButton variant="secondary" @click="notesOpen = false">Cancelar</AppButton>
                    <AppButton type="submit" form="policy-notes-form" :loading="notesForm.processing">Salvar</AppButton>
                </template>
            </AppModal>
        </template>
    </div>
</template>
