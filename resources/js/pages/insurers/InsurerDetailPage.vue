<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { insurersApi } from '@/api';
import ActivityTimeline from '@/components/activity/ActivityTimeline.vue';
import ActiveBadge from '@/components/ui/ActiveBadge.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppCard from '@/components/ui/AppCard.vue';
import DetailList from '@/components/ui/DetailList.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { useAsyncData } from '@/composables/useAsyncData';
import { useAuthStore } from '@/stores/auth';
import { useConfirmStore } from '@/stores/confirm';
import { useNotificationsStore } from '@/stores/notifications';
import { formatDocument, formatPhone, formatZipCode } from '@/utils/format';

const props = defineProps({
    id: { type: String, required: true },
});

const auth = useAuthStore();
const confirm = useConfirmStore();
const notifications = useNotificationsStore();
const router = useRouter();
const deleting = ref(false);

const { data: insurer, loading, error, reload } = useAsyncData((id) => insurersApi.get(id), () => props.id);

const details = computed(() => {
    const i = insurer.value;

    return [
        { key: 'legal_name', label: 'Razão social', value: i.legal_name },
        { key: 'cnpj', label: 'CNPJ', value: formatDocument(i.cnpj) },
        { key: 'phone', label: 'Telefone', value: i.phone ? formatPhone(i.phone) : null },
        { key: 'email', label: 'E-mail', value: i.email },
        { key: 'website', label: 'Site', value: i.website },
        { key: 'policies', label: 'Apólices vinculadas', value: i.policies_count },
        { key: 'address', label: 'Endereço', value: [i.street, i.number, i.complement, i.district].filter(Boolean).join(', '), wide: true },
        { key: 'city', label: 'Cidade/UF', value: i.city ? `${i.city}/${i.state ?? ''}` : null },
        { key: 'zip', label: 'CEP', value: i.zip_code ? formatZipCode(i.zip_code) : null },
    ];
});

async function remove() {
    const confirmed = await confirm.ask({
        title: 'Excluir seguradora',
        message: `Excluir ${insurer.value.trade_name}? Seguradoras com apólices em andamento não podem ser excluídas; nesse caso, inative o cadastro.`,
        confirmLabel: 'Excluir',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    deleting.value = true;

    try {
        await insurersApi.remove(props.id);
        notifications.success('Seguradora excluída.');
        router.push({ name: 'insurers.index' });
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
        <LoadingState v-else-if="loading && !insurer" />

        <template v-else-if="insurer">
            <PageHeader :title="insurer.trade_name" :breadcrumbs="[{ label: 'Seguradoras', to: { name: 'insurers.index' } }, { label: insurer.trade_name }]">
                <template #subtitle><ActiveBadge :active="insurer.is_active" /></template>
                <template #actions>
                    <template v-if="auth.can('insurers.manage')">
                        <AppButton variant="danger-ghost" icon="trash" :loading="deleting" @click="remove">Excluir</AppButton>
                        <AppButton variant="secondary" icon="pencil" :to="{ name: 'insurers.edit', params: { id } }">Editar</AppButton>
                    </template>
                    <AppButton variant="secondary" :to="{ path: '/policies', query: { insurer_id: insurer.id } }">Ver apólices</AppButton>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <AppCard title="Dados da seguradora">
                        <DetailList :items="details" :columns="3">
                            <template #item-website="{ item }">
                                <a v-if="item.value" :href="item.value" target="_blank" rel="noopener noreferrer" class="text-brand-700 hover:underline">
                                    {{ item.value }}
                                </a>
                                <span v-else>—</span>
                            </template>
                        </DetailList>
                    </AppCard>
                    <AppCard v-if="insurer.notes" title="Observações">
                        <p class="text-sm whitespace-pre-line text-slate-700">{{ insurer.notes }}</p>
                    </AppCard>
                </div>
                <AppCard title="Histórico">
                    <ActivityTimeline subject-type="insurer" :subject-id="insurer.id" />
                </AppCard>
            </div>
        </template>
    </div>
</template>
