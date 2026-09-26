<script setup>
/*
 * Transição de status de um sinistro (PATCH /claims/{id}/status). As opções
 * são as `allowed_transitions` do próprio sinistro; o valor aprovado é pedido
 * apenas para a transição "approved", e observações podem acompanhar qualquer
 * mudança — elas ficam registradas no histórico de status.
 */
import { computed, watch } from 'vue';
import { claimsApi } from '@/api';
import FormAlert from '../form/FormAlert.vue';
import MoneyInput from '../form/MoneyInput.vue';
import TextareaInput from '../form/TextareaInput.vue';
import AppButton from '../ui/AppButton.vue';
import AppModal from '../ui/AppModal.vue';
import { useForm } from '@/composables/useForm';
import { useNotificationsStore } from '@/stores/notifications';

const props = defineProps({
    claim: { type: Object, required: true },
    status: { type: Object, default: null },
});

const emit = defineEmits(['close', 'changed']);

const notifications = useNotificationsStore();
const form = useForm({ approved_amount: '', notes: '' });
const requiresAmount = computed(() => props.status?.value === 'approved');

watch(
    () => props.status,
    (status) => {
        if (status) {
            form.reset({ approved_amount: props.claim.estimated_amount ?? '', notes: '' });
        }
    },
);

async function submit() {
    const updated = await form.submit((fields) =>
        claimsApi.changeStatus(props.claim.id, {
            status: props.status.value,
            notes: fields.notes || null,
            ...(requiresAmount.value ? { approved_amount: fields.approved_amount } : {}),
        }),
    );

    if (updated) {
        notifications.success(`Sinistro ${updated.protocol}: ${updated.status.label}.`);
        emit('changed', updated);
    }
}
</script>

<template>
    <AppModal :open="status !== null" :title="`Alterar para ${status?.label ?? ''}`" @close="emit('close')">
        <form id="claim-status-form" class="space-y-4" novalidate @submit.prevent="submit">
            <p class="text-sm text-slate-600">
                Sinistro <strong>{{ claim.protocol }}</strong>: {{ claim.status.label }} → <strong>{{ status?.label }}</strong>
            </p>
            <FormAlert :message="form.generalError" />
            <MoneyInput
                v-if="requiresAmount"
                v-model="form.fields.approved_amount"
                label="Valor aprovado"
                required
                autofocus
                :error="form.error('approved_amount')"
            />
            <TextareaInput
                v-model="form.fields.notes"
                label="Observações"
                hint="Ficam registradas no histórico do sinistro."
                :error="form.error('notes') || form.error('status')"
            />
        </form>
        <template #footer>
            <AppButton variant="secondary" @click="emit('close')">Cancelar</AppButton>
            <AppButton
                type="submit"
                form="claim-status-form"
                :variant="status?.value === 'denied' ? 'danger' : 'primary'"
                :loading="form.processing"
            >
                Confirmar
            </AppButton>
        </template>
    </AppModal>
</template>
