<script setup>
/*
 * Registro de pagamento de uma parcela (POST /installments/{id}/payment).
 * A data de pagamento vem preenchida com hoje; as regras (somente parcelas
 * pendentes, data não futura) são do backend e seus erros aparecem aqui.
 */
import { watch } from 'vue';
import { installmentsApi } from '@/api';
import FormAlert from '../form/FormAlert.vue';
import TextInput from '../form/TextInput.vue';
import AppButton from '../ui/AppButton.vue';
import AppModal from '../ui/AppModal.vue';
import { useForm } from '@/composables/useForm';
import { useNotificationsStore } from '@/stores/notifications';
import { formatDate, formatMoney, todayIso } from '@/utils/format';
import { dateNotAfterToday, required } from '@/utils/validation';

const props = defineProps({
    installment: { type: Object, default: null },
});

const emit = defineEmits(['close', 'paid']);

const notifications = useNotificationsStore();
const form = useForm({ paid_on: todayIso() }, { rules: { paid_on: [required(), dateNotAfterToday()] } });

watch(
    () => props.installment,
    (installment) => {
        if (installment) {
            form.reset({ paid_on: todayIso() });
        }
    },
);

async function submit() {
    const paid = await form.submit((fields) => installmentsApi.pay(props.installment.id, fields.paid_on));

    if (paid) {
        notifications.success(`Pagamento da parcela ${paid.number} registrado.`);
        emit('paid', paid);
    }
}
</script>

<template>
    <AppModal :open="installment !== null" title="Registrar pagamento" size="sm" @close="emit('close')">
        <form v-if="installment" id="pay-installment-form" class="space-y-4" novalidate @submit.prevent="submit">
            <FormAlert :message="form.generalError" />
            <dl class="grid grid-cols-3 gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-500">Parcela</dt>
                    <dd class="font-medium text-slate-900">{{ installment.number }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Vencimento</dt>
                    <dd class="font-medium text-slate-900">{{ formatDate(installment.due_date) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Valor</dt>
                    <dd class="font-medium text-slate-900">{{ formatMoney(installment.amount) }}</dd>
                </div>
            </dl>
            <TextInput
                v-model="form.fields.paid_on"
                type="date"
                label="Data do pagamento"
                required
                autofocus
                :max="todayIso()"
                :error="form.error('paid_on') || form.error('status')"
            />
        </form>
        <template #footer>
            <AppButton variant="secondary" @click="emit('close')">Cancelar</AppButton>
            <AppButton type="submit" form="pay-installment-form" :loading="form.processing">Confirmar pagamento</AppButton>
        </template>
    </AppModal>
</template>
