<script setup>
/*
 * Detalhe do sinistro: dados da ocorrência, apólice e cliente, ações de
 * transição (allowed_transitions da API), edição dos dados descritivos
 * enquanto `is_editable`, exclusão (claims.delete) e histórico de status.
 * O status nunca é alterado pelo formulário de edição.
 */
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { claimsApi } from '@/api';
import ClaimStatusDialog from '@/components/claims/ClaimStatusDialog.vue';
import ClaimStatusHistory from '@/components/claims/ClaimStatusHistory.vue';
import FormAlert from '@/components/form/FormAlert.vue';
import MoneyInput from '@/components/form/MoneyInput.vue';
import TextareaInput from '@/components/form/TextareaInput.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppCard from '@/components/ui/AppCard.vue';
import AppModal from '@/components/ui/AppModal.vue';
import DetailList from '@/components/ui/DetailList.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useAsyncData } from '@/composables/useAsyncData';
import { useForm } from '@/composables/useForm';
import { useAuthStore } from '@/stores/auth';
import { useConfirmStore } from '@/stores/confirm';
import { useNotificationsStore } from '@/stores/notifications';
import { formatDate, formatMoney } from '@/utils/format';

const props = defineProps({
    id: { type: String, required: true },
});

const auth = useAuthStore();
const confirm = useConfirmStore();
const notifications = useNotificationsStore();
const router = useRouter();

const { data: claim, loading, error, reload } = useAsyncData((id) => claimsApi.get(id), () => props.id);

const nextStatus = ref(null);
const editOpen = ref(false);
const deleting = ref(false);
const editForm = useForm({ description: '', estimated_amount: '', notes: '' });

const details = computed(() => {
    const c = claim.value;

    return [
        { key: 'occurred_on', label: 'Data da ocorrência', value: formatDate(c.occurred_on) },
        { key: 'reported_on', label: 'Data da comunicação', value: formatDate(c.reported_on) },
        { key: 'estimated_amount', label: 'Valor estimado', value: formatMoney(c.estimated_amount) },
        { key: 'approved_amount', label: 'Valor aprovado', value: formatMoney(c.approved_amount) },
        { key: 'description', label: 'Descrição', value: c.description, wide: true },
    ];
});

const policyDetails = computed(() => {
    const p = claim.value.policy;

    return [
        { key: 'number', label: 'Apólice', value: p?.number },
        { key: 'client', label: 'Cliente', value: p?.client?.name },
        { key: 'product', label: 'Modalidade', value: p?.product?.name },
        { key: 'insurer', label: 'Seguradora', value: p?.insurer?.trade_name },
        { key: 'term', label: 'Vigência', value: p ? `${formatDate(p.starts_on)} a ${formatDate(p.ends_on)}` : null },
    ];
});

function onStatusChanged() {
    nextStatus.value = null;
    reload();
}

function openEdit() {
    editForm.reset({
        description: claim.value.description,
        estimated_amount: claim.value.estimated_amount,
        notes: claim.value.notes ?? '',
    });
    editOpen.value = true;
}

async function saveEdit() {
    const saved = await editForm.submit((fields) => claimsApi.update(props.id, { ...fields, notes: fields.notes || null }, 'patch'));

    if (saved) {
        editOpen.value = false;
        notifications.success('Sinistro atualizado.');
        reload();
    }
}

async function remove() {
    const confirmed = await confirm.ask({
        title: 'Excluir sinistro',
        message: `Excluir o sinistro ${claim.value.protocol}? O histórico de status é preservado para auditoria.`,
        confirmLabel: 'Excluir',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    deleting.value = true;

    try {
        await claimsApi.remove(props.id);
        notifications.success('Sinistro excluído.');
        router.push({ name: 'claims.index' });
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
        <LoadingState v-else-if="loading && !claim" />

        <template v-else-if="claim">
            <PageHeader
                :title="`Sinistro ${claim.protocol}`"
                :breadcrumbs="[{ label: 'Sinistros', to: { name: 'claims.index' } }, { label: claim.protocol }]"
            >
                <template #subtitle>
                    <span class="inline-flex items-center gap-2">
                        <StatusBadge :status="claim.status" />
                        <span>{{ claim.policy?.client?.name }}</span>
                    </span>
                </template>
                <template #actions>
                    <AppButton v-if="auth.can('claims.delete')" variant="danger-ghost" icon="trash" :loading="deleting" @click="remove">Excluir</AppButton>
                    <AppButton v-if="auth.can('claims.manage') && claim.is_editable" variant="secondary" icon="pencil" @click="openEdit">Editar</AppButton>
                </template>
            </PageHeader>

            <div v-if="auth.can('claims.manage') && claim.allowed_transitions.length" class="mb-6 rounded-xl border border-slate-200 bg-white px-5 py-3 shadow-xs">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-600">Próximo passo do atendimento:</p>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Alterar status do sinistro">
                        <AppButton
                            v-for="status in claim.allowed_transitions"
                            :key="status.value"
                            size="sm"
                            :variant="status.value === 'denied' ? 'danger-ghost' : 'secondary'"
                            @click="nextStatus = status"
                        >
                            {{ status.label }}
                        </AppButton>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <AppCard title="Ocorrência">
                        <DetailList :items="details" />
                    </AppCard>
                    <AppCard title="Apólice">
                        <DetailList :items="policyDetails" :columns="3">
                            <template #item-number="{ item }">
                                <RouterLink :to="{ name: 'policies.show', params: { id: claim.policy_id } }" class="text-brand-700 hover:underline">
                                    {{ item.value }}
                                </RouterLink>
                            </template>
                            <template #item-client="{ item }">
                                <RouterLink :to="{ name: 'clients.show', params: { id: claim.policy.client_id } }" class="text-brand-700 hover:underline">
                                    {{ item.value }}
                                </RouterLink>
                            </template>
                        </DetailList>
                    </AppCard>
                    <AppCard v-if="claim.notes" title="Observações internas">
                        <p class="text-sm whitespace-pre-line text-slate-700">{{ claim.notes }}</p>
                    </AppCard>
                </div>

                <AppCard title="Histórico de status">
                    <ClaimStatusHistory :history="claim.status_history" />
                </AppCard>
            </div>

            <ClaimStatusDialog :claim="claim" :status="nextStatus" @close="nextStatus = null" @changed="onStatusChanged" />

            <AppModal :open="editOpen" title="Editar sinistro" @close="editOpen = false">
                <form id="claim-edit-form" class="space-y-4" novalidate @submit.prevent="saveEdit">
                    <FormAlert :message="editForm.generalError" />
                    <TextareaInput v-model="editForm.fields.description" label="Descrição" required :rows="4" autofocus :error="editForm.error('description')" />
                    <MoneyInput v-model="editForm.fields.estimated_amount" label="Valor estimado" required :error="editForm.error('estimated_amount')" />
                    <TextareaInput v-model="editForm.fields.notes" label="Observações internas" :error="editForm.error('notes')" />
                </form>
                <template #footer>
                    <AppButton variant="secondary" @click="editOpen = false">Cancelar</AppButton>
                    <AppButton type="submit" form="claim-edit-form" :loading="editForm.processing">Salvar</AppButton>
                </template>
            </AppModal>
        </template>
    </div>
</template>
