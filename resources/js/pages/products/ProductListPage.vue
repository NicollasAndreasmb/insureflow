<script setup>
/*
 * Modalidades de seguro. Por ter poucos campos, criação e edição acontecem em
 * um modal sobre a própria listagem. O identificador (slug) é gerado pela API
 * a partir do nome quando não é informado.
 */
import { ref } from 'vue';
import { productsApi } from '@/api';
import DataTable from '@/components/data/DataTable.vue';
import SearchInput from '@/components/data/SearchInput.vue';
import TablePagination from '@/components/data/TablePagination.vue';
import CheckboxInput from '@/components/form/CheckboxInput.vue';
import FormAlert from '@/components/form/FormAlert.vue';
import SelectInput from '@/components/form/SelectInput.vue';
import TextareaInput from '@/components/form/TextareaInput.vue';
import TextInput from '@/components/form/TextInput.vue';
import ActiveBadge from '@/components/ui/ActiveBadge.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { useForm } from '@/composables/useForm';
import { usePaginatedList } from '@/composables/usePaginatedList';
import { useAuthStore } from '@/stores/auth';
import { useConfirmStore } from '@/stores/confirm';
import { useNotificationsStore } from '@/stores/notifications';
import { formatNumber } from '@/utils/format';
import { maxLength, required } from '@/utils/validation';

const auth = useAuthStore();
const confirm = useConfirmStore();
const notifications = useNotificationsStore();

const list = usePaginatedList(productsApi.list, { filterKeys: ['search', 'is_active'], defaultSort: 'name' });

const form = useForm(
    { name: '', description: '', is_active: true },
    { rules: { name: [required(), maxLength(100)], description: [maxLength(5000)] } },
);
const editing = ref(null);
const modalOpen = ref(false);
const deletingId = ref(null);

const activeOptions = [
    { value: '1', label: 'Ativas' },
    { value: '0', label: 'Inativas' },
];

const columns = [
    { key: 'name', label: 'Modalidade', sortable: true },
    { key: 'description', label: 'Descrição', class: 'hidden lg:table-cell' },
    { key: 'policies_count', label: 'Apólices', align: 'right' },
    { key: 'is_active', label: 'Situação' },
    ...(auth.can('insurance_products.manage') ? [{ key: 'actions', label: 'Ações', align: 'right' }] : []),
];

function openForm(product = null) {
    editing.value = product;
    form.reset(product ? { name: product.name, description: product.description ?? '', is_active: product.is_active } : { name: '', description: '', is_active: true });
    modalOpen.value = true;
}

async function save() {
    const saved = await form.submit((fields) =>
        editing.value ? productsApi.update(editing.value.id, { ...fields, slug: editing.value.slug }) : productsApi.create(fields),
    );

    if (saved) {
        modalOpen.value = false;
        notifications.success(editing.value ? 'Modalidade atualizada.' : 'Modalidade cadastrada.');
        list.reload();
    }
}

async function remove(product) {
    const confirmed = await confirm.ask({
        title: 'Excluir modalidade',
        message: `Excluir ${product.name}? Modalidades com apólices em andamento não podem ser excluídas.`,
        confirmLabel: 'Excluir',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    deletingId.value = product.id;

    try {
        await productsApi.remove(product.id);
        notifications.success('Modalidade excluída.');
        list.reload();
    } catch (exception) {
        notifications.error(exception.message);
    } finally {
        deletingId.value = null;
    }
}
</script>

<template>
    <div>
        <PageHeader title="Modalidades" subtitle="Tipos de seguro oferecidos pela corretora.">
            <template #actions>
                <AppButton v-if="auth.can('insurance_products.manage')" icon="plus" @click="openForm()">Nova modalidade</AppButton>
            </template>
        </PageHeader>

        <section class="rounded-xl border border-slate-200 bg-white shadow-xs">
            <form class="grid grid-cols-1 gap-3 border-b border-slate-200 p-4 sm:grid-cols-3" role="search" @submit.prevent>
                <SearchInput
                    v-model="list.filters.search"
                    class="sm:col-span-2"
                    label="Buscar modalidades"
                    placeholder="Nome da modalidade"
                    @update:model-value="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.is_active"
                    aria-label="Situação"
                    placeholder="Todas as situações"
                    :options="activeOptions"
                    @update:model-value="list.applyFilters()"
                />
            </form>

            <ErrorState v-if="list.error" :error="list.error" @retry="list.reload" />
            <DataTable
                v-else
                :columns="columns"
                :rows="list.items"
                :loading="list.loading"
                :sort="list.sort"
                caption="Lista de modalidades"
                @sort="list.setSort"
            >
                <template #cell-name="{ row }">
                    <span class="font-medium text-slate-900">{{ row.name }}</span>
                </template>
                <template #cell-description="{ row }">
                    <span class="line-clamp-2 text-slate-600">{{ row.description ?? '—' }}</span>
                </template>
                <template #cell-policies_count="{ row }">
                    <RouterLink :to="{ path: '/policies', query: { insurance_product_id: row.id } }" class="text-brand-800 hover:underline">
                        {{ formatNumber(row.policies_count) }}
                    </RouterLink>
                </template>
                <template #cell-is_active="{ row }"><ActiveBadge :active="row.is_active" /></template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-1">
                        <AppButton size="sm" variant="ghost" icon="pencil" :aria-label="`Editar ${row.name}`" @click="openForm(row)" />
                        <AppButton
                            size="sm"
                            variant="danger-ghost"
                            icon="trash"
                            :aria-label="`Excluir ${row.name}`"
                            :loading="deletingId === row.id"
                            @click="remove(row)"
                        />
                    </div>
                </template>
                <template #empty>
                    <EmptyState :title="list.hasActiveFilters ? 'Nenhuma modalidade encontrada' : 'Nenhuma modalidade cadastrada'" icon="tag" />
                </template>
            </DataTable>
            <TablePagination :meta="list.meta" @change="list.setPage" />
        </section>

        <AppModal :open="modalOpen" :title="editing ? 'Editar modalidade' : 'Nova modalidade'" @close="modalOpen = false">
            <form id="product-form" class="space-y-4" novalidate @submit.prevent="save">
                <FormAlert :message="form.generalError" />
                <TextInput v-model="form.fields.name" label="Nome" required autofocus :error="form.error('name') || form.error('slug')" />
                <TextareaInput v-model="form.fields.description" label="Descrição" :error="form.error('description')" />
                <CheckboxInput v-model="form.fields.is_active" label="Modalidade ativa" description="Modalidades inativas não aceitam novas apólices." />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="modalOpen = false">Cancelar</AppButton>
                <AppButton type="submit" form="product-form" :loading="form.processing">Salvar</AppButton>
            </template>
        </AppModal>
    </div>
</template>
