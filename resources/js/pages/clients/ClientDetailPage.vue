<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { clientsApi } from '@/api';
import ActivityTimeline from '@/components/activity/ActivityTimeline.vue';
import DataTable from '@/components/data/DataTable.vue';
import ActiveBadge from '@/components/ui/ActiveBadge.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppCard from '@/components/ui/AppCard.vue';
import DetailList from '@/components/ui/DetailList.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useAsyncData } from '@/composables/useAsyncData';
import { useAuthStore } from '@/stores/auth';
import { useConfirmStore } from '@/stores/confirm';
import { useNotificationsStore } from '@/stores/notifications';
import { formatDate, formatDocument, formatMoney, formatPhone, formatZipCode } from '@/utils/format';

const props = defineProps({
    id: { type: String, required: true },
});

const auth = useAuthStore();
const confirm = useConfirmStore();
const notifications = useNotificationsStore();
const router = useRouter();
const deleting = ref(false);

const { data: client, loading, error, reload } = useAsyncData((id) => clientsApi.get(id), () => props.id);

const isCompany = computed(() => client.value?.person_type.value === 'company');

const identification = computed(() => [
    { key: 'type', label: 'Tipo', value: client.value.person_type.label },
    { key: 'document', label: isCompany.value ? 'CNPJ' : 'CPF', value: formatDocument(client.value.document) },
    { key: 'birth', label: isCompany.value ? 'Fundação' : 'Nascimento', value: formatDate(client.value.birth_date) },
    { key: 'email', label: 'E-mail', value: client.value.email },
    { key: 'phone', label: 'Telefone', value: client.value.phone ? formatPhone(client.value.phone) : null },
    { key: 'mobile', label: 'Celular', value: client.value.mobile ? formatPhone(client.value.mobile) : null },
]);

const address = computed(() => {
    const c = client.value;
    const street = [c.street, c.number, c.complement].filter(Boolean).join(', ');

    return [
        { key: 'street', label: 'Logradouro', value: street, wide: true },
        { key: 'district', label: 'Bairro', value: c.district },
        { key: 'city', label: 'Cidade/UF', value: c.city ? `${c.city}/${c.state ?? ''}` : null },
        { key: 'zip', label: 'CEP', value: c.zip_code ? formatZipCode(c.zip_code) : null },
    ];
});

const policyColumns = [
    { key: 'number', label: 'Apólice' },
    { key: 'product', label: 'Modalidade', class: 'hidden md:table-cell' },
    { key: 'term', label: 'Vigência', class: 'hidden sm:table-cell' },
    { key: 'premium', label: 'Prêmio', align: 'right' },
    { key: 'status', label: 'Status' },
];

async function remove() {
    const confirmed = await confirm.ask({
        title: 'Excluir cliente',
        message: `Excluir ${client.value.name}? Clientes com apólices em andamento não podem ser excluídos; nesse caso, inative o cadastro.`,
        confirmLabel: 'Excluir',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    deleting.value = true;

    try {
        await clientsApi.remove(props.id);
        notifications.success('Cliente excluído.');
        router.push({ name: 'clients.index' });
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
        <LoadingState v-else-if="loading && !client" />

        <template v-else-if="client">
            <PageHeader :title="client.name" :breadcrumbs="[{ label: 'Clientes', to: { name: 'clients.index' } }, { label: client.name }]">
                <template #subtitle>
                    <span class="inline-flex items-center gap-2">
                        <ActiveBadge :active="client.is_active" />
                        <span>{{ client.policies_count }} apólice(s) · {{ client.claims_count }} sinistro(s)</span>
                    </span>
                </template>
                <template #actions>
                    <AppButton v-if="auth.can('clients.delete')" variant="danger-ghost" icon="trash" :loading="deleting" @click="remove">
                        Excluir
                    </AppButton>
                    <AppButton v-if="auth.can('clients.update')" variant="secondary" icon="pencil" :to="{ name: 'clients.edit', params: { id } }">
                        Editar
                    </AppButton>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <AppCard title="Dados cadastrais">
                        <DetailList :items="identification" :columns="3" />
                    </AppCard>
                    <AppCard title="Endereço">
                        <DetailList :items="address" />
                    </AppCard>
                    <AppCard title="Apólices" :padded="false">
                        <template #actions>
                            <AppButton
                                v-if="auth.can('policies.manage') && client.is_active"
                                size="sm"
                                icon="plus"
                                :to="{ path: '/policies/new', query: { client_id: client.id } }"
                            >
                                Nova apólice
                            </AppButton>
                        </template>
                        <DataTable :columns="policyColumns" :rows="client.policies" caption="Apólices do cliente">
                            <template #cell-number="{ row }">
                                <RouterLink :to="`/policies/${row.id}`" class="font-medium text-brand-800 hover:underline">
                                    {{ row.number }}
                                </RouterLink>
                                <p class="text-xs text-slate-500">{{ row.insurer?.trade_name }}</p>
                            </template>
                            <template #cell-product="{ row }">{{ row.product?.name }}</template>
                            <template #cell-term="{ row }">{{ formatDate(row.starts_on) }} a {{ formatDate(row.ends_on) }}</template>
                            <template #cell-premium="{ row }">{{ formatMoney(row.premium) }}</template>
                            <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
                            <template #empty>
                                <EmptyState title="Nenhuma apólice para este cliente" icon="shield" />
                            </template>
                        </DataTable>
                    </AppCard>
                </div>

                <div class="space-y-6">
                    <AppCard v-if="client.notes" title="Observações">
                        <p class="text-sm whitespace-pre-line text-slate-700">{{ client.notes }}</p>
                    </AppCard>
                    <AppCard title="Histórico">
                        <ActivityTimeline subject-type="client" :subject-id="client.id" />
                    </AppCard>
                </div>
            </div>
        </template>
    </div>
</template>
