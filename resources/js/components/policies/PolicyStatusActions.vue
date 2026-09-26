<script setup>
/*
 * Botões de transição de status da apólice. As opções são exatamente as
 * `allowed_transitions` devolvidas pela API (PolicyStatus::allowedTransitions);
 * o frontend não conhece a máquina de estados. Toda transição pede
 * confirmação e usa PATCH /policies/{id}/status. Condições que dependem de
 * datas (ex.: expirar antes do fim da vigência) são recusadas pelo backend e
 * a mensagem é exibida ao usuário.
 */
import { ref } from 'vue';
import { policiesApi } from '@/api';
import AppButton from '../ui/AppButton.vue';
import { useConfirmStore } from '@/stores/confirm';
import { useNotificationsStore } from '@/stores/notifications';

const props = defineProps({
    policy: { type: Object, required: true },
});

const emit = defineEmits(['changed']);

const confirm = useConfirmStore();
const notifications = useNotificationsStore();
const pending = ref(null);

const consequences = {
    pending: 'As parcelas serão geradas a partir do prêmio.',
    active: 'Se ainda não houver parcelas, elas serão geradas a partir do prêmio.',
    cancelled: 'As parcelas pendentes serão canceladas. Esta ação não pode ser desfeita.',
    expired: 'A apólice passará a aceitar apenas alterações de observações.',
};

async function change(status) {
    const confirmed = await confirm.ask({
        title: `Alterar status para ${status.label}`,
        message: `Apólice ${props.policy.number}: ${props.policy.status.label} → ${status.label}. ${consequences[status.value] ?? ''}`,
        confirmLabel: status.label,
        danger: status.value === 'cancelled',
    });

    if (!confirmed) {
        return;
    }

    pending.value = status.value;

    try {
        const updated = await policiesApi.changeStatus(props.policy.id, status.value);
        notifications.success(`Status alterado para ${updated.status.label}.`);
        emit('changed', updated);
    } catch (exception) {
        notifications.error(exception.message);
    } finally {
        pending.value = null;
    }
}
</script>

<template>
    <div v-if="policy.allowed_transitions.length" class="flex flex-wrap gap-2" role="group" aria-label="Alterar status da apólice">
        <AppButton
            v-for="status in policy.allowed_transitions"
            :key="status.value"
            size="sm"
            :variant="status.value === 'cancelled' ? 'danger-ghost' : 'secondary'"
            :loading="pending === status.value"
            :disabled="pending !== null && pending !== status.value"
            @click="change(status)"
        >
            {{ status.value === 'cancelled' ? 'Cancelar apólice' : `Marcar como ${status.label.toLowerCase()}` }}
        </AppButton>
    </div>
</template>
