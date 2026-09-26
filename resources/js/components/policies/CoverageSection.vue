<script setup>
/*
 * Coberturas da apólice: listagem e gestão em modal usando os endpoints
 * aninhados (/policies/{id}/coverages) e diretos (/coverages/{id}). A gestão
 * aparece somente quando o usuário tem coverages.manage e a apólice está
 * editável (`is_editable`, calculado pela API).
 */
import { computed, ref } from 'vue';
import { coveragesApi, policiesApi } from '@/api';
import DataTable from '../data/DataTable.vue';
import CheckboxInput from '../form/CheckboxInput.vue';
import FormAlert from '../form/FormAlert.vue';
import MoneyInput from '../form/MoneyInput.vue';
import TextareaInput from '../form/TextareaInput.vue';
import TextInput from '../form/TextInput.vue';
import ActiveBadge from '../ui/ActiveBadge.vue';
import AppButton from '../ui/AppButton.vue';
import AppCard from '../ui/AppCard.vue';
import AppModal from '../ui/AppModal.vue';
import EmptyState from '../ui/EmptyState.vue';
import { useForm } from '@/composables/useForm';
import { useAuthStore } from '@/stores/auth';
import { useConfirmStore } from '@/stores/confirm';
import { useNotificationsStore } from '@/stores/notifications';
import { formatMoney } from '@/utils/format';

const props = defineProps({
    policy: { type: Object, required: true },
});

const emit = defineEmits(['changed']);

const auth = useAuthStore();
const confirm = useConfirmStore();
const notifications = useNotificationsStore();

const EMPTY = { name: '', description: '', coverage_limit: '', deductible: '', additional_premium: '0.00', is_active: true };
const form = useForm(EMPTY);
const editing = ref(null);
const modalOpen = ref(false);
const deletingId = ref(null);

const canManage = computed(() => auth.can('coverages.manage') && props.policy.is_editable);

const columns = computed(() => [
    { key: 'name', label: 'Cobertura' },
    { key: 'coverage_limit', label: 'Limite', align: 'right' },
    { key: 'deductible', label: 'Franquia', align: 'right', class: 'hidden md:table-cell' },
    { key: 'additional_premium', label: 'Prêmio adicional', align: 'right', class: 'hidden lg:table-cell' },
    { key: 'is_active', label: 'Situação', class: 'hidden sm:table-cell' },
    ...(canManage.value ? [{ key: 'actions', label: 'Ações', align: 'right' }] : []),
]);

function openForm(coverage = null) {
    editing.value = coverage;
    form.reset(
        coverage
            ? {
                  name: coverage.name,
                  description: coverage.description ?? '',
                  coverage_limit: coverage.coverage_limit,
                  deductible: coverage.deductible ?? '',
                  additional_premium: coverage.additional_premium,
                  is_active: coverage.is_active,
              }
            : EMPTY,
    );
    modalOpen.value = true;
}

async function save() {
    const saved = await form.submit((fields) => {
        const payload = { ...fields, deductible: fields.deductible === '' ? null : fields.deductible };

        return editing.value ? coveragesApi.update(editing.value.id, payload) : policiesApi.createCoverage(props.policy.id, payload);
    });

    if (saved) {
        modalOpen.value = false;
        notifications.success(editing.value ? 'Cobertura atualizada.' : 'Cobertura adicionada.');
        emit('changed');
    }
}

async function remove(coverage) {
    const confirmed = await confirm.ask({
        title: 'Remover cobertura',
        message: `Remover a cobertura "${coverage.name}" da apólice ${props.policy.number}?`,
        confirmLabel: 'Remover',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    deletingId.value = coverage.id;

    try {
        await coveragesApi.remove(coverage.id);
        notifications.success('Cobertura removida.');
        emit('changed');
    } catch (exception) {
        notifications.error(exception.message);
    } finally {
        deletingId.value = null;
    }
}
</script>

<template>
    <AppCard title="Coberturas" :padded="false">
        <template v-if="canManage" #actions>
            <AppButton size="sm" icon="plus" @click="openForm()">Adicionar cobertura</AppButton>
        </template>

        <DataTable :columns="columns" :rows="policy.coverages" caption="Coberturas da apólice">
            <template #cell-name="{ row }">
                <span class="font-medium text-slate-900">{{ row.name }}</span>
                <p v-if="row.description" class="text-xs text-slate-500">{{ row.description }}</p>
            </template>
            <template #cell-coverage_limit="{ row }">{{ formatMoney(row.coverage_limit) }}</template>
            <template #cell-deductible="{ row }">{{ formatMoney(row.deductible) }}</template>
            <template #cell-additional_premium="{ row }">{{ formatMoney(row.additional_premium) }}</template>
            <template #cell-is_active="{ row }"><ActiveBadge :active="row.is_active" /></template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-1">
                    <AppButton size="sm" variant="ghost" icon="pencil" :aria-label="`Editar cobertura ${row.name}`" @click="openForm(row)" />
                    <AppButton
                        size="sm"
                        variant="danger-ghost"
                        icon="trash"
                        :aria-label="`Remover cobertura ${row.name}`"
                        :loading="deletingId === row.id"
                        @click="remove(row)"
                    />
                </div>
            </template>
            <template #empty>
                <EmptyState title="Nenhuma cobertura cadastrada" icon="shield" />
            </template>
        </DataTable>

        <AppModal :open="modalOpen" :title="editing ? 'Editar cobertura' : 'Adicionar cobertura'" @close="modalOpen = false">
            <form id="coverage-form" class="space-y-4" novalidate @submit.prevent="save">
                <FormAlert :message="form.generalError" />
                <TextInput v-model="form.fields.name" label="Nome" required autofocus :error="form.error('name')" />
                <TextareaInput v-model="form.fields.description" label="Descrição" :rows="2" :error="form.error('description')" />
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <MoneyInput v-model="form.fields.coverage_limit" label="Limite" required :error="form.error('coverage_limit')" />
                    <MoneyInput v-model="form.fields.deductible" label="Franquia" :error="form.error('deductible')" />
                    <MoneyInput v-model="form.fields.additional_premium" label="Prêmio adicional" :error="form.error('additional_premium')" />
                </div>
                <CheckboxInput v-model="form.fields.is_active" label="Cobertura ativa" />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="modalOpen = false">Cancelar</AppButton>
                <AppButton type="submit" form="coverage-form" :loading="form.processing">Salvar</AppButton>
            </template>
        </AppModal>
    </AppCard>
</template>
