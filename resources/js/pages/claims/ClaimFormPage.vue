<script setup>
/*
 * Abertura de sinistro. A elegibilidade da apólice e a ocorrência dentro da
 * vigência são regras do backend: aqui a apólice é apenas buscada e o
 * resultado (ou o motivo da recusa) é exibido no campo correspondente.
 */
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { claimsApi, policiesApi } from '@/api';
import FormAlert from '@/components/form/FormAlert.vue';
import MoneyInput from '@/components/form/MoneyInput.vue';
import RemoteSelect from '@/components/form/RemoteSelect.vue';
import TextareaInput from '@/components/form/TextareaInput.vue';
import TextInput from '@/components/form/TextInput.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppCard from '@/components/ui/AppCard.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { useForm } from '@/composables/useForm';
import { useNotificationsStore } from '@/stores/notifications';
import { formatDate, todayIso } from '@/utils/format';
import { dateNotAfterToday, dateNotBefore, maxLength, money, required } from '@/utils/validation';

const route = useRoute();
const router = useRouter();
const notifications = useNotificationsStore();

const form = useForm({
    policy_id: null,
    occurred_on: '',
    reported_on: todayIso(),
    description: '',
    estimated_amount: '',
    notes: '',
}, {
    rules: {
        policy_id: [required('Selecione a apólice.')],
        occurred_on: [required(), dateNotAfterToday()],
        reported_on: [dateNotAfterToday(), dateNotBefore('occurred_on', 'A comunicação não pode ser anterior à ocorrência.')],
        description: [required(), maxLength(5000)],
        estimated_amount: [required(), money()],
        notes: [maxLength(5000)],
    },
});

const selectedPolicy = ref(null);

function toOption(policy) {
    return {
        value: policy.id,
        label: `${policy.number} · ${policy.client?.name ?? ''}`,
        description: `${policy.product?.name ?? ''} · ${policy.status.label} · ${formatDate(policy.starts_on)} a ${formatDate(policy.ends_on)}`,
    };
}

async function searchPolicies(term) {
    const response = await policiesApi.list({ search: term, per_page: 10, sort: '-starts_on' });

    return response.data.map(toOption);
}

async function submit() {
    const claim = await form.submit((fields) =>
        claimsApi.create({ ...fields, notes: fields.notes || null, reported_on: fields.reported_on || undefined }),
    );

    if (claim) {
        notifications.success(`Sinistro ${claim.protocol} aberto.`);
        router.push({ name: 'claims.show', params: { id: claim.id } });
    }
}

onMounted(async () => {
    if (route.query.policy_id) {
        try {
            const policy = await policiesApi.get(route.query.policy_id);
            selectedPolicy.value = toOption(policy);
            form.fields.policy_id = policy.id;
        } catch (exception) {
            notifications.error(exception.message);
        }
    }
});
</script>

<template>
    <div>
        <PageHeader title="Abrir sinistro" :breadcrumbs="[{ label: 'Sinistros', to: { name: 'claims.index' } }, { label: 'Abrir' }]" />

        <form class="space-y-6" novalidate @submit.prevent="submit">
            <FormAlert :message="form.generalError" />

            <AppCard title="Apólice e datas">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <RemoteSelect
                        v-model="form.fields.policy_id"
                        class="sm:col-span-2"
                        label="Apólice"
                        required
                        :fetcher="searchPolicies"
                        :selected="selectedPolicy"
                        placeholder="Busque pelo número da apólice ou nome do cliente"
                        hint="A apólice precisa estar ativa, ou expirada se a ocorrência foi durante a vigência."
                        :error="form.error('policy_id')"
                    />
                    <TextInput
                        v-model="form.fields.occurred_on"
                        type="date"
                        label="Data da ocorrência"
                        required
                        :max="todayIso()"
                        :error="form.error('occurred_on')"
                    />
                    <TextInput
                        v-model="form.fields.reported_on"
                        type="date"
                        label="Data da comunicação"
                        :max="todayIso()"
                        :error="form.error('reported_on')"
                    />
                </div>
            </AppCard>

            <AppCard title="Ocorrência">
                <div class="space-y-4">
                    <TextareaInput v-model="form.fields.description" label="Descrição" required :rows="4" :error="form.error('description')" />
                    <MoneyInput v-model="form.fields.estimated_amount" class="sm:max-w-xs" label="Valor estimado" required :error="form.error('estimated_amount')" />
                    <TextareaInput v-model="form.fields.notes" label="Observações internas" :error="form.error('notes')" />
                </div>
            </AppCard>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <AppButton variant="secondary" :to="{ name: 'claims.index' }">Cancelar</AppButton>
                <AppButton type="submit" :loading="form.processing">Abrir sinistro</AppButton>
            </div>
        </form>
    </div>
</template>
