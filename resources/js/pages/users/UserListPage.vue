<script setup>
/*
 * Administração de usuários (somente perfis com users.manage). Criação e
 * edição em modal; na edição a senha é opcional e mantida quando vazia. As
 * proteções contra o administrador alterar o próprio perfil, desativar ou
 * excluir a própria conta são do backend; o botão de excluir apenas não é
 * oferecido na própria linha.
 */
import { computed, ref } from 'vue';
import { usersApi } from '@/api';
import DataTable from '@/components/data/DataTable.vue';
import SearchInput from '@/components/data/SearchInput.vue';
import TablePagination from '@/components/data/TablePagination.vue';
import CheckboxInput from '@/components/form/CheckboxInput.vue';
import FormAlert from '@/components/form/FormAlert.vue';
import SelectInput from '@/components/form/SelectInput.vue';
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
import { useOptionsStore } from '@/stores/options';
import { formatDate } from '@/utils/format';

const auth = useAuthStore();
const options = useOptionsStore();
const confirm = useConfirmStore();
const notifications = useNotificationsStore();

const list = usePaginatedList(usersApi.list, { filterKeys: ['search', 'role', 'is_active'], defaultSort: 'name' });

const EMPTY = { name: '', email: '', password: '', role: 'broker', is_active: true };
const form = useForm(EMPTY);
const editing = ref(null);
const modalOpen = ref(false);
const deletingId = ref(null);
const isSelf = computed(() => editing.value?.id === auth.user?.id);

const activeOptions = [
    { value: '1', label: 'Ativos' },
    { value: '0', label: 'Inativos' },
];

const columns = [
    { key: 'name', label: 'Usuário', sortable: true },
    { key: 'role', label: 'Perfil' },
    { key: 'created_at', label: 'Criado em', sortable: true, class: 'hidden md:table-cell' },
    { key: 'is_active', label: 'Situação' },
    { key: 'actions', label: 'Ações', align: 'right' },
];

function openForm(user = null) {
    editing.value = user;
    form.reset(user ? { name: user.name, email: user.email, password: '', role: user.role.value, is_active: user.is_active } : EMPTY);
    modalOpen.value = true;
}

async function save() {
    const saved = await form.submit((fields) => {
        const payload = { ...fields, password: fields.password || null };

        return editing.value ? usersApi.update(editing.value.id, payload) : usersApi.create(payload);
    });

    if (saved) {
        modalOpen.value = false;
        notifications.success(editing.value ? 'Usuário atualizado.' : 'Usuário criado.');
        list.reload();
    }
}

async function remove(user) {
    const confirmed = await confirm.ask({
        title: 'Excluir usuário',
        message: `Excluir o acesso de ${user.name}? O histórico de ações do usuário é preservado.`,
        confirmLabel: 'Excluir',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    deletingId.value = user.id;

    try {
        await usersApi.remove(user.id);
        notifications.success('Usuário excluído.');
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
        <PageHeader title="Usuários" subtitle="Pessoas com acesso ao sistema e seus perfis.">
            <template #actions>
                <AppButton icon="plus" @click="openForm()">Novo usuário</AppButton>
            </template>
        </PageHeader>

        <section class="rounded-xl border border-slate-200 bg-white shadow-xs">
            <form class="grid grid-cols-1 gap-3 border-b border-slate-200 p-4 sm:grid-cols-3" role="search" @submit.prevent>
                <SearchInput
                    v-model="list.filters.search"
                    label="Buscar usuários"
                    placeholder="Nome ou e-mail"
                    @update:model-value="list.applyFilters()"
                />
                <SelectInput
                    v-model="list.filters.role"
                    aria-label="Perfil"
                    placeholder="Todos os perfis"
                    :options="options.list('user_roles')"
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
                caption="Lista de usuários"
                @sort="list.setSort"
            >
                <template #cell-name="{ row }">
                    <span class="font-medium text-slate-900">{{ row.name }}</span>
                    <span v-if="row.id === auth.user?.id" class="ml-1.5 text-xs text-slate-500">(você)</span>
                    <p class="text-xs text-slate-500">{{ row.email }}</p>
                </template>
                <template #cell-role="{ row }">{{ row.role.label }}</template>
                <template #cell-created_at="{ row }">{{ formatDate(row.created_at) }}</template>
                <template #cell-is_active="{ row }"><ActiveBadge :active="row.is_active" /></template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-1">
                        <AppButton size="sm" variant="ghost" icon="pencil" :aria-label="`Editar ${row.name}`" @click="openForm(row)" />
                        <AppButton
                            v-if="row.id !== auth.user?.id"
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
                    <EmptyState title="Nenhum usuário encontrado" icon="user" />
                </template>
            </DataTable>
            <TablePagination :meta="list.meta" @change="list.setPage" />
        </section>

        <AppModal :open="modalOpen" :title="editing ? 'Editar usuário' : 'Novo usuário'" @close="modalOpen = false">
            <form id="user-form" class="space-y-4" novalidate @submit.prevent="save">
                <FormAlert :message="form.generalError" />
                <TextInput v-model="form.fields.name" label="Nome" required autofocus :error="form.error('name')" />
                <TextInput v-model="form.fields.email" type="email" label="E-mail" required autocomplete="off" :error="form.error('email')" />
                <TextInput
                    v-model="form.fields.password"
                    type="password"
                    label="Senha"
                    autocomplete="new-password"
                    :required="!editing"
                    :hint="editing ? 'Deixe em branco para manter a senha atual.' : 'Mínimo de 8 caracteres, com letras e números.'"
                    :error="form.error('password')"
                />
                <SelectInput v-model="form.fields.role" label="Perfil" required :options="options.list('user_roles')" :error="form.error('role')" />
                <CheckboxInput
                    v-model="form.fields.is_active"
                    label="Acesso ativo"
                    description="Usuários inativos não conseguem entrar no sistema."
                    :error="form.error('is_active')"
                />
                <p v-if="isSelf" class="text-xs text-slate-500">Você está editando a sua própria conta.</p>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="modalOpen = false">Cancelar</AppButton>
                <AppButton type="submit" form="user-form" :loading="form.processing">Salvar</AppButton>
            </template>
        </AppModal>
    </div>
</template>
