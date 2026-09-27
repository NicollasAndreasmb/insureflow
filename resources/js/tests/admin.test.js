import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { activityLogsApi, usersApi } from '@/api';
import { ApiError } from '@/api/http';
import ActivityLogPage from '@/pages/activity/ActivityLogPage.vue';
import UserListPage from '@/pages/users/UserListPage.vue';
import { mountPage } from './support';

vi.mock('@/api', () => ({
    usersApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
    activityLogsApi: { list: vi.fn() },
}));

const meta = (total) => ({ current_page: 1, last_page: 1, from: 1, to: total, total });

beforeEach(() => vi.clearAllMocks());

describe('UserListPage', () => {
    const users = [
        { id: 1, name: 'Ana', email: 'admin@insureflow.test', role: { value: 'admin', label: 'Administrador' }, is_active: true, created_at: '2026-09-01T10:00:00-03:00' },
        { id: 2, name: 'Bruno', email: 'bruno@insureflow.test', role: { value: 'broker', label: 'Corretor' }, is_active: true, created_at: '2026-09-02T10:00:00-03:00' },
    ];

    it('does not offer deleting the logged-in account', async () => {
        usersApi.list.mockResolvedValue({ data: users, meta: meta(2) });

        const { wrapper } = await mountPage(UserListPage, { permissions: { 'users.manage': true } });

        expect(wrapper.find('[aria-label="Excluir Ana"]').exists()).toBe(false);
        expect(wrapper.find('[aria-label="Excluir Bruno"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('(você)');
    });

    it('keeps the password when left blank on edit and shows API errors', async () => {
        usersApi.list.mockResolvedValue({ data: users, meta: meta(2) });
        usersApi.update.mockRejectedValue(
            new ApiError({ status: 422, message: 'Os dados informados são inválidos.', errors: { role: ['Você não pode alterar o seu próprio perfil.'] } }),
        );
        const { wrapper } = await mountPage(UserListPage, { permissions: { 'users.manage': true } });

        await wrapper.find('[aria-label="Editar Ana"]').trigger('click');
        await wrapper.find('#user-form').trigger('submit');
        await flushPromises();

        expect(usersApi.update).toHaveBeenCalledWith(1, expect.objectContaining({ password: null, role: 'admin' }));
        expect(wrapper.text()).toContain('Você não pode alterar o seu próprio perfil.');
    });
});

describe('ActivityLogPage', () => {
    it('renders readable entries and filters by entity through the API', async () => {
        activityLogsApi.list.mockResolvedValue({
            data: [
                {
                    id: 5,
                    action: { value: 'updated', label: 'Alteração' },
                    subject_type: 'policy',
                    subject_id: 7,
                    user: { id: 2, name: 'Bruno' },
                    changes: { old: { premium: '100.00' }, new: { premium: '150.00' } },
                    created_at: '2026-09-26T10:00:00-03:00',
                },
            ],
            meta: meta(1),
        });

        const { wrapper, router } = await mountPage(ActivityLogPage, { route: '/activity-logs?subject_type=policy' });

        expect(activityLogsApi.list).toHaveBeenCalledWith(expect.objectContaining({ subject_type: 'policy', sort: '-created_at' }), expect.anything());
        expect(wrapper.text()).toContain('Apólice #7');
        expect(wrapper.text()).toContain('Prêmio');
        expect(wrapper.find('a[href="/policies/7"]').exists()).toBe(true);
        expect(router.currentRoute.value.query.subject_type).toBe('policy');
    });
});
