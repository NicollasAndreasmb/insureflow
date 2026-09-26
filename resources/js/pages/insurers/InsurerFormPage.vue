<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { insurersApi } from '@/api';
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
    legal_name: '',
    trade_name: '',
    cnpj: '',
    phone: '',
    email: '',
    website: '',
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
const stateOptions = options.list('states').map((state) => ({ value: state, label: state }));

async function load() {
    loading.value = true;
    loadError.value = null;

    try {
        const insurer = await insurersApi.get(props.id);
        form.reset(Object.fromEntries(Object.keys(EMPTY).map((key) => [key, insurer[key] ?? EMPTY[key]])));
    } catch (error) {
        loadError.value = error;
    } finally {
        loading.value = false;
    }
}

async function submit() {
    const insurer = await form.submit((fields) => {
        const payload = Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, value === '' ? null : value]));

        return isEditing.value ? insurersApi.update(props.id, payload) : insurersApi.create(payload);
    });

    if (insurer) {
        notifications.success(isEditing.value ? 'Seguradora atualizada.' : 'Seguradora cadastrada.');
        router.push({ name: 'insurers.show', params: { id: insurer.id } });
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
            :title="isEditing ? 'Editar seguradora' : 'Nova seguradora'"
            :breadcrumbs="[{ label: 'Seguradoras', to: { name: 'insurers.index' } }, { label: isEditing ? 'Editar' : 'Nova' }]"
        />

        <ErrorState v-if="loadError" :error="loadError" @retry="load" />
        <LoadingState v-else-if="loading" />

        <form v-else class="space-y-6" novalidate @submit.prevent="submit">
            <FormAlert :message="form.generalError" />

            <AppCard title="Identificação">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <TextInput v-model="form.fields.trade_name" label="Nome fantasia" required :error="form.error('trade_name')" />
                    <TextInput v-model="form.fields.legal_name" label="Razão social" required :error="form.error('legal_name')" />
                    <MaskedInput v-model="form.fields.cnpj" mask="cnpj" label="CNPJ" required :error="form.error('cnpj')" />
                </div>
            </AppCard>

            <AppCard title="Contato">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <MaskedInput v-model="form.fields.phone" mask="phone" label="Telefone" :error="form.error('phone')" />
                    <TextInput v-model="form.fields.email" type="email" label="E-mail" :error="form.error('email')" />
                    <TextInput v-model="form.fields.website" type="url" label="Site" placeholder="https://" :error="form.error('website')" />
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
                    <SelectInput v-model="form.fields.state" label="UF" placeholder="—" :options="stateOptions" :error="form.error('state')" />
                </div>
            </AppCard>

            <AppCard title="Observações e situação">
                <div class="space-y-4">
                    <TextareaInput v-model="form.fields.notes" label="Observações" :error="form.error('notes')" />
                    <CheckboxInput v-model="form.fields.is_active" label="Seguradora ativa" :error="form.error('is_active')" />
                </div>
            </AppCard>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <AppButton variant="secondary" :to="isEditing ? { name: 'insurers.show', params: { id } } : { name: 'insurers.index' }">Cancelar</AppButton>
                <AppButton type="submit" :loading="form.processing">{{ isEditing ? 'Salvar alterações' : 'Cadastrar seguradora' }}</AppButton>
            </div>
        </form>
    </div>
</template>
