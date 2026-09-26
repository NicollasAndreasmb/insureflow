<script setup>
/*
 * Cadastro e edição de clientes (a edição envia o registro completo via PUT).
 * CPF/CNPJ, telefones e CEP são digitados com máscara, mas enviados apenas
 * com dígitos; a validação de verdade (dígitos verificadores, unicidade) é do
 * backend, e seus erros aparecem em cada campo.
 */
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { clientsApi } from '@/api';
import CheckboxInput from '@/components/form/CheckboxInput.vue';
import FormAlert from '@/components/form/FormAlert.vue';
import MaskedInput from '@/components/form/MaskedInput.vue';
import SelectInput from '@/components/form/SelectInput.vue';
import TextareaInput from '@/components/form/TextareaInput.vue';
import TextInput from '@/components/form/TextInput.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppCard from '@/components/ui/AppCard.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { useForm } from '@/composables/useForm';
import { useNotificationsStore } from '@/stores/notifications';
import { useOptionsStore } from '@/stores/options';

const props = defineProps({
    id: { type: String, default: null },
});

const router = useRouter();
const options = useOptionsStore();
const notifications = useNotificationsStore();

const EMPTY = {
    person_type: 'individual',
    name: '',
    document: '',
    email: '',
    phone: '',
    mobile: '',
    birth_date: '',
    zip_code: '',
    street: '',
    number: '',
    complement: '',
    district: '',
    city: '',
    state: '',
    notes: '',
    is_active: true,
};

const form = useForm(EMPTY);
const loading = ref(Boolean(props.id));
const loadError = ref(null);

const isEditing = computed(() => Boolean(props.id));
const isCompany = computed(() => form.fields.person_type === 'company');
const stateOptions = options.list('states').map((state) => ({ value: state, label: state }));

async function load() {
    loading.value = true;
    loadError.value = null;

    try {
        const client = await clientsApi.get(props.id);
        form.reset(
            Object.fromEntries(
                Object.keys(EMPTY).map((key) => {
                    const value = key === 'person_type' ? client.person_type.value : client[key];

                    return [key, value ?? EMPTY[key]];
                }),
            ),
        );
    } catch (error) {
        loadError.value = error;
    } finally {
        loading.value = false;
    }
}

async function submit() {
    const client = await form.submit((fields) => {
        const payload = Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, value === '' ? null : value]));

        return isEditing.value ? clientsApi.update(props.id, payload) : clientsApi.create(payload);
    });

    if (client) {
        notifications.success(isEditing.value ? 'Cliente atualizado.' : 'Cliente cadastrado.');
        router.push({ name: 'clients.show', params: { id: client.id } });
    }
}

onMounted(() => {
    if (isEditing.value) {
        load();
    }
});
</script>

<template>
    <div>
        <PageHeader
            :title="isEditing ? 'Editar cliente' : 'Novo cliente'"
            :breadcrumbs="[
                { label: 'Clientes', to: { name: 'clients.index' } },
                ...(isEditing ? [{ label: form.fields.name || 'Cliente', to: { name: 'clients.show', params: { id } } }] : []),
                { label: isEditing ? 'Editar' : 'Novo' },
            ]"
        />

        <ErrorState v-if="loadError" :error="loadError" @retry="load" />
        <LoadingState v-else-if="loading" />

        <form v-else class="space-y-6" novalidate @submit.prevent="submit">
            <FormAlert :message="form.generalError" />

            <AppCard title="Identificação">
                <fieldset class="mb-5">
                    <legend class="mb-2 text-sm font-medium text-slate-700">Tipo de pessoa</legend>
                    <div class="flex flex-wrap gap-3">
                        <label
                            v-for="type in options.list('person_types')"
                            :key="type.value"
                            class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm ring-1 ring-inset has-checked:bg-brand-50 has-checked:ring-brand-600 has-focus-visible:outline-2 has-focus-visible:outline-brand-600"
                            :class="form.fields.person_type === type.value ? 'text-brand-900' : 'text-slate-700 ring-slate-300'"
                        >
                            <input v-model="form.fields.person_type" type="radio" name="person_type" :value="type.value" class="accent-brand-700" />
                            {{ type.label }}
                        </label>
                    </div>
                    <p v-if="form.error('person_type')" class="mt-1 text-xs text-red-600">{{ form.error('person_type') }}</p>
                </fieldset>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <TextInput
                        v-model="form.fields.name"
                        class="sm:col-span-2"
                        :label="isCompany ? 'Razão social' : 'Nome completo'"
                        required
                        maxlength="150"
                        :error="form.error('name')"
                    />
                    <MaskedInput
                        v-model="form.fields.document"
                        :mask="isCompany ? 'cnpj' : 'cpf'"
                        :label="isCompany ? 'CNPJ' : 'CPF'"
                        required
                        :error="form.error('document')"
                    />
                    <TextInput
                        v-model="form.fields.birth_date"
                        type="date"
                        :label="isCompany ? 'Data de fundação' : 'Data de nascimento'"
                        :error="form.error('birth_date')"
                    />
                </div>
            </AppCard>

            <AppCard title="Contato">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <TextInput v-model="form.fields.email" type="email" label="E-mail" autocomplete="off" :error="form.error('email')" />
                    <MaskedInput v-model="form.fields.phone" mask="phone" label="Telefone" :error="form.error('phone')" />
                    <MaskedInput v-model="form.fields.mobile" mask="phone" label="Celular" :error="form.error('mobile')" />
                </div>
            </AppCard>

            <AppCard title="Endereço">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                    <MaskedInput v-model="form.fields.zip_code" class="sm:col-span-2" mask="zip" label="CEP" :error="form.error('zip_code')" />
                    <TextInput v-model="form.fields.street" class="sm:col-span-4" label="Logradouro" :error="form.error('street')" />
                    <TextInput v-model="form.fields.number" class="sm:col-span-2" label="Número" :error="form.error('number')" />
                    <TextInput v-model="form.fields.complement" class="sm:col-span-4" label="Complemento" :error="form.error('complement')" />
                    <TextInput v-model="form.fields.district" class="sm:col-span-2" label="Bairro" :error="form.error('district')" />
                    <TextInput v-model="form.fields.city" class="sm:col-span-3" label="Cidade" :error="form.error('city')" />
                    <SelectInput
                        v-model="form.fields.state"
                        label="UF"
                        placeholder="—"
                        :options="stateOptions"
                        :error="form.error('state')"
                    />
                </div>
            </AppCard>

            <AppCard title="Observações e situação">
                <div class="space-y-4">
                    <TextareaInput v-model="form.fields.notes" label="Observações" :error="form.error('notes')" />
                    <CheckboxInput
                        v-model="form.fields.is_active"
                        label="Cliente ativo"
                        description="Clientes inativos não podem receber novas apólices."
                        :error="form.error('is_active')"
                    />
                </div>
            </AppCard>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <AppButton variant="secondary" :to="isEditing ? { name: 'clients.show', params: { id } } : { name: 'clients.index' }">
                    Cancelar
                </AppButton>
                <AppButton type="submit" :loading="form.processing">{{ isEditing ? 'Salvar alterações' : 'Cadastrar cliente' }}</AppButton>
            </div>
        </form>
    </div>
</template>
