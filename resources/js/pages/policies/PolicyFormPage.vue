<script setup>
/*
 * Cadastro e edição de apólices.
 *
 * - O status inicial vem de /api/options (policy_initial_statuses); depois do
 *   cadastro, o status só muda pelas ações de transição na página de detalhe.
 * - Cliente é escolhido por busca na API (RemoteSelect); seguradora, modalidade
 *   e corretor por listas curtas. Corretores só são listados para quem pode
 *   gerenciar usuários; para um corretor, a API assume ele próprio.
 * - Na edição (PATCH), a API recusa alterar prêmio/parcelas depois de algum
 *   pagamento e regenera as parcelas quando permitido; essas respostas são
 *   exibidas como vieram.
 * - Coberturas podem ser informadas no cadastro; depois, são geridas na
 *   página da apólice.
 */
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { clientsApi, policiesApi } from '@/api';
import FormAlert from '@/components/form/FormAlert.vue';
import MoneyInput from '@/components/form/MoneyInput.vue';
import RemoteSelect from '@/components/form/RemoteSelect.vue';
import SelectInput from '@/components/form/SelectInput.vue';
import TextareaInput from '@/components/form/TextareaInput.vue';
import TextInput from '@/components/form/TextInput.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppCard from '@/components/ui/AppCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { useForm } from '@/composables/useForm';
import { fetchBrokerOptions, fetchInsurerOptions, fetchProductOptions } from '@/composables/useLookups';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import { useOptionsStore } from '@/stores/options';
import { formatDocument } from '@/utils/format';
import { dateAfter, integerBetween, maxLength, money, numberBetween, required } from '@/utils/validation';

const props = defineProps({
    id: { type: String, default: null },
});

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const options = useOptionsStore();
const notifications = useNotificationsStore();

const isEditing = computed(() => Boolean(props.id));
const canChooseBroker = computed(() => auth.can('users.manage'));

const form = useForm({
    number: '',
    client_id: null,
    insurer_id: '',
    insurance_product_id: '',
    broker_id: '',
    starts_on: '',
    ends_on: '',
    premium: '',
    deductible: '',
    commission_rate: '',
    payment_method: '',
    installments_count: 1,
    status: 'quote',
    notes: '',
    coverages: [],
}, {
    rules: {
        number: [required(), maxLength(50)],
        client_id: [required('Selecione o cliente.')],
        insurer_id: [required('Selecione a seguradora.')],
        insurance_product_id: [required('Selecione a modalidade.')],
        starts_on: [required()],
        ends_on: [required(), dateAfter('starts_on', 'O fim da vigência deve ser posterior ao início.')],
        premium: [required(), money()],
        deductible: [money()],
        commission_rate: [required(), numberBetween(0, 100)],
        payment_method: [required('Selecione a forma de pagamento.')],
        installments_count: [required(), integerBetween(1, 12)],
        notes: [maxLength(5000)],
    },
});

const policy = ref(null);
const selectedClient = ref(null);
const insurerOptions = ref([]);
const productOptions = ref([]);
const brokerOptions = ref([]);
const loading = ref(true);
const loadError = ref(null);

const installmentOptions = Array.from({ length: 12 }, (_, index) => ({ value: index + 1, label: index === 0 ? 'À vista' : `${index + 1}x` }));

async function searchClients(term) {
    const response = await clientsApi.list({ search: term, is_active: isEditing.value ? '' : 1, per_page: 10, sort: 'name' });

    return response.data.map((client) => ({ value: client.id, label: client.name, description: formatDocument(client.document) }));
}

function addOneYear(date) {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year + 1, month - 1, day)).toISOString().slice(0, 10);
}

watch(
    () => form.fields.starts_on,
    (startsOn) => {
        if (!isEditing.value && startsOn && !form.fields.ends_on) {
            form.fields.ends_on = addOneYear(startsOn);
        }
    },
);

function addCoverage() {
    form.fields.coverages.push({ name: '', coverage_limit: '', deductible: '', additional_premium: '0.00' });
}

async function load() {
    loading.value = true;
    loadError.value = null;

    try {
        const activeOnly = !isEditing.value;
        [insurerOptions.value, productOptions.value, brokerOptions.value] = await Promise.all([
            fetchInsurerOptions({ activeOnly }),
            fetchProductOptions({ activeOnly }),
            canChooseBroker.value ? fetchBrokerOptions() : Promise.resolve([]),
        ]);

        if (isEditing.value) {
            policy.value = await policiesApi.get(props.id);
            const p = policy.value;
            selectedClient.value = { value: p.client_id, label: p.client.name };
            ensureOption(insurerOptions, p.insurer_id, p.insurer?.trade_name);
            ensureOption(productOptions, p.insurance_product_id, p.product?.name);
            ensureOption(brokerOptions, p.broker_id, p.broker?.name);
            form.reset({
                number: p.number,
                client_id: p.client_id,
                insurer_id: p.insurer_id,
                insurance_product_id: p.insurance_product_id,
                broker_id: p.broker_id,
                starts_on: p.starts_on,
                ends_on: p.ends_on,
                premium: p.premium,
                deductible: p.deductible ?? '',
                commission_rate: p.commission_rate,
                payment_method: p.payment_method.value,
                installments_count: p.installments_count,
                notes: p.notes ?? '',
            });
        } else if (route.query.client_id) {
            const client = await clientsApi.get(route.query.client_id);
            selectedClient.value = { value: client.id, label: client.name };
            form.fields.client_id = client.id;
        }
    } catch (error) {
        loadError.value = error;
    } finally {
        loading.value = false;
    }
}

function ensureOption(list, value, label) {
    if (value && !list.value.some((option) => option.value === value)) {
        list.value = [...list.value, { value, label: label ?? `#${value}` }];
    }
}

function payload(fields) {
    const data = { ...fields, deductible: fields.deductible === '' ? null : fields.deductible, notes: fields.notes || null };

    if (!canChooseBroker.value || data.broker_id === '') {
        delete data.broker_id;
    }

    if (isEditing.value) {
        delete data.status;
        delete data.coverages;
    } else {
        data.coverages = fields.coverages.map((coverage) => ({ ...coverage, deductible: coverage.deductible === '' ? null : coverage.deductible }));
    }

    return data;
}

async function submit() {
    const saved = await form.submit((fields) =>
        isEditing.value ? policiesApi.update(props.id, payload(fields), 'patch') : policiesApi.create(payload(fields)),
    );

    if (saved) {
        notifications.success(isEditing.value ? 'Apólice atualizada.' : 'Apólice cadastrada.');
        router.push({ name: 'policies.show', params: { id: saved.id } });
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader
            :title="isEditing ? `Editar apólice ${policy?.number ?? ''}` : 'Nova apólice'"
            :breadcrumbs="[
                { label: 'Apólices', to: { name: 'policies.index' } },
                ...(isEditing ? [{ label: policy?.number ?? 'Apólice', to: { name: 'policies.show', params: { id } } }] : []),
                { label: isEditing ? 'Editar' : 'Nova' },
            ]"
        />

        <ErrorState v-if="loadError" :error="loadError" @retry="load" />
        <LoadingState v-else-if="loading" />

        <EmptyState
            v-else-if="isEditing && policy && !policy.is_editable"
            :title="`Apólice ${policy.status.label.toLowerCase()}`"
            description="Apólices canceladas ou expiradas não podem ser editadas. As observações podem ser alteradas na página da apólice."
            icon="shield"
        >
            <AppButton variant="secondary" :to="{ name: 'policies.show', params: { id } }">Voltar à apólice</AppButton>
        </EmptyState>

        <form v-else class="space-y-6" novalidate @submit.prevent="submit">
            <FormAlert :message="form.generalError" />

            <AppCard title="Partes do contrato">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <RemoteSelect
                        v-model="form.fields.client_id"
                        class="md:col-span-2"
                        label="Cliente"
                        required
                        :fetcher="searchClients"
                        :selected="selectedClient"
                        placeholder="Busque por nome ou CPF/CNPJ"
                        :error="form.error('client_id')"
                    />
                    <SelectInput
                        v-model="form.fields.insurer_id"
                        label="Seguradora"
                        required
                        placeholder="Selecione"
                        :options="insurerOptions"
                        :error="form.error('insurer_id')"
                    />
                    <SelectInput
                        v-model="form.fields.insurance_product_id"
                        label="Modalidade"
                        required
                        placeholder="Selecione"
                        :options="productOptions"
                        :error="form.error('insurance_product_id')"
                    />
                    <TextInput v-model="form.fields.number" label="Número da apólice" required maxlength="50" :error="form.error('number')" />
                    <SelectInput
                        v-if="canChooseBroker"
                        v-model="form.fields.broker_id"
                        label="Corretor responsável"
                        required
                        placeholder="Selecione"
                        :options="brokerOptions"
                        :error="form.error('broker_id')"
                    />
                    <p v-else-if="!isEditing" class="self-end pb-2 text-sm text-slate-500">
                        O corretor responsável será {{ auth.user?.name }}.
                    </p>
                </div>
                <p v-if="form.error('broker_id') && !canChooseBroker" class="mt-2 text-xs text-red-600">{{ form.error('broker_id') }}</p>
            </AppCard>

            <AppCard title="Vigência e valores">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <TextInput v-model="form.fields.starts_on" type="date" label="Início da vigência" required :error="form.error('starts_on')" />
                    <TextInput v-model="form.fields.ends_on" type="date" label="Fim da vigência" required :error="form.error('ends_on')" />
                    <MoneyInput v-model="form.fields.premium" label="Prêmio total" required :error="form.error('premium')" />
                    <MoneyInput v-model="form.fields.deductible" label="Franquia" :error="form.error('deductible')" />
                    <TextInput
                        v-model="form.fields.commission_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        inputmode="decimal"
                        label="Comissão (%)"
                        required
                        hint="O valor da comissão é calculado pelo sistema."
                        :error="form.error('commission_rate')"
                    />
                    <SelectInput
                        v-model="form.fields.payment_method"
                        label="Forma de pagamento"
                        required
                        placeholder="Selecione"
                        :options="options.list('payment_methods')"
                        :error="form.error('payment_method')"
                    />
                    <SelectInput
                        v-model.number="form.fields.installments_count"
                        label="Parcelas"
                        required
                        :options="installmentOptions"
                        :hint="isEditing ? 'Alterar prêmio ou parcelas regenera as parcelas ainda não pagas.' : ''"
                        :error="form.error('installments_count')"
                    />
                    <SelectInput
                        v-if="!isEditing"
                        v-model="form.fields.status"
                        label="Situação inicial"
                        required
                        :options="options.list('policy_initial_statuses')"
                        hint="Cotações não geram parcelas."
                        :error="form.error('status')"
                    />
                </div>
            </AppCard>

            <AppCard v-if="!isEditing" title="Coberturas" description="Opcional. Também podem ser incluídas depois, na página da apólice.">
                <div class="space-y-4">
                    <fieldset
                        v-for="(coverage, index) in form.fields.coverages"
                        :key="index"
                        class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_auto]"
                    >
                        <legend class="sr-only">Cobertura {{ index + 1 }}</legend>
                        <TextInput v-model="coverage.name" label="Cobertura" required :error="form.error(`coverages.${index}.name`)" />
                        <MoneyInput v-model="coverage.coverage_limit" label="Limite" required :error="form.error(`coverages.${index}.coverage_limit`)" />
                        <MoneyInput v-model="coverage.deductible" label="Franquia" :error="form.error(`coverages.${index}.deductible`)" />
                        <MoneyInput
                            v-model="coverage.additional_premium"
                            label="Prêmio adicional"
                            :error="form.error(`coverages.${index}.additional_premium`)"
                        />
                        <AppButton
                            class="self-end"
                            variant="danger-ghost"
                            icon="trash"
                            :aria-label="`Remover cobertura ${index + 1}`"
                            @click="form.fields.coverages.splice(index, 1)"
                        />
                    </fieldset>
                    <AppButton variant="secondary" size="sm" icon="plus" @click="addCoverage">Adicionar cobertura</AppButton>
                </div>
            </AppCard>

            <AppCard title="Observações">
                <TextareaInput v-model="form.fields.notes" label="Observações" :error="form.error('notes')" />
            </AppCard>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <AppButton variant="secondary" :to="isEditing ? { name: 'policies.show', params: { id } } : { name: 'policies.index' }">Cancelar</AppButton>
                <AppButton type="submit" :loading="form.processing">{{ isEditing ? 'Salvar alterações' : 'Cadastrar apólice' }}</AppButton>
            </div>
        </form>
    </div>
</template>
