import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { claimsApi } from '@/api';
import { ApiError } from '@/api/http';
import ClaimStatusDialog from '@/components/claims/ClaimStatusDialog.vue';
import ClaimDetailPage from '@/pages/claims/ClaimDetailPage.vue';
import { mountPage } from './support';

vi.mock('@/api', () => ({
    claimsApi: { get: vi.fn(), changeStatus: vi.fn(), update: vi.fn(), remove: vi.fn() },
}));

const claim = (overrides = {}) => ({
    id: 9,
    protocol: 'SIN-2026-000042',
    status: { value: 'under_review', label: 'Em análise' },
    allowed_transitions: [
        { value: 'pending_documents', label: 'Documentação pendente' },
        { value: 'approved', label: 'Aprovado' },
        { value: 'denied', label: 'Negado' },
    ],
    is_editable: true,
    policy_id: 7,
    policy: { id: 7, number: '01.23', client_id: 3, client: { name: 'Maria Souza' }, product: { name: 'Seguro Auto' }, starts_on: '2026-01-01', ends_on: '2027-01-01' },
    occurred_on: '2026-09-20',
    reported_on: '2026-09-21',
    description: 'Colisão traseira.',
    estimated_amount: '8500.00',
    approved_amount: null,
    notes: null,
    status_history: [
        { id: 1, from_status: null, to_status: { value: 'open', label: 'Aberto' }, user: { id: 1, name: 'Carla' }, notes: 'Sinistro aberto.', created_at: '2026-09-21T10:00:00-03:00' },
        {
            id: 2,
            from_status: { value: 'open', label: 'Aberto' },
            to_status: { value: 'under_review', label: 'Em análise' },
            user: { id: 1, name: 'Carla' },
            notes: null,
            created_at: '2026-09-22T10:00:00-03:00',
        },
    ],
    ...overrides,
});

const routes = [
    { path: '/claims', name: 'claims.index', component: { template: '<div />' } },
    { path: '/policies/:id', name: 'policies.show', component: { template: '<div />' } },
    { path: '/clients/:id', name: 'clients.show', component: { template: '<div />' } },
];

beforeEach(() => vi.clearAllMocks());

describe('ClaimStatusDialog', () => {
    it('asks for the approved amount only when approving', async () => {
        claimsApi.changeStatus.mockResolvedValue(claim({ status: { value: 'approved', label: 'Aprovado' } }));
        const { wrapper } = await mountPage(ClaimStatusDialog, { props: { claim: claim(), status: null } });

        await wrapper.setProps({ status: { value: 'pending_documents', label: 'Documentação pendente' } });
        expect(wrapper.find('input[inputmode="decimal"]').exists()).toBe(false);

        await wrapper.setProps({ status: { value: 'approved', label: 'Aprovado' } });
        const amount = wrapper.find('input[inputmode="decimal"]');
        expect(amount.exists()).toBe(true);

        await amount.setValue('7.200,50');
        await wrapper.find('textarea').setValue('Laudo aprovado.');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(claimsApi.changeStatus).toHaveBeenCalledWith(9, { status: 'approved', notes: 'Laudo aprovado.', approved_amount: '7200.50' });
        expect(wrapper.emitted('changed')).toHaveLength(1);
    });

    it('shows backend validation errors for the transition', async () => {
        claimsApi.changeStatus.mockRejectedValue(
            new ApiError({ status: 422, message: 'Os dados informados são inválidos.', errors: { approved_amount: ['O campo valor aprovado é obrigatório.'] } }),
        );
        const { wrapper } = await mountPage(ClaimStatusDialog, { props: { claim: claim(), status: null } });

        await wrapper.setProps({ status: { value: 'approved', label: 'Aprovado' } });
        await wrapper.find('input[inputmode="decimal"]').setValue('1.500,00');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('O campo valor aprovado é obrigatório.');
        expect(wrapper.emitted('changed')).toBeUndefined();
    });

    it('requires the approved amount before calling the API', async () => {
        const { wrapper } = await mountPage(ClaimStatusDialog, { props: { claim: claim(), status: null } });

        await wrapper.setProps({ status: { value: 'approved', label: 'Aprovado' } });
        await wrapper.find('input[inputmode="decimal"]').setValue('');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(claimsApi.changeStatus).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Este campo é obrigatório.');
    });
});

describe('ClaimDetailPage', () => {
    it('shows the status history and only the transitions returned by the API', async () => {
        claimsApi.get.mockResolvedValue(claim());
        const { wrapper } = await mountPage(ClaimDetailPage, { props: { id: '9' }, routes, permissions: { 'claims.manage': true } });
        const transitionButtons = wrapper.find('[aria-label="Alterar status do sinistro"]').findAll('button').map((button) => button.text());

        expect(transitionButtons).toEqual(['Documentação pendente', 'Aprovado', 'Negado']);
        expect(wrapper.text()).toContain('Sinistro aberto.');
        expect(wrapper.text()).toContain('Carla');
        expect(wrapper.text()).toContain('Editar');
    });

    it('is read-only for closed claims and users without permission', async () => {
        claimsApi.get.mockResolvedValue(claim({ status: { value: 'closed', label: 'Encerrado' }, allowed_transitions: [], is_editable: false }));
        const { wrapper } = await mountPage(ClaimDetailPage, { props: { id: '9' }, routes, permissions: { 'claims.manage': true } });

        expect(wrapper.find('[aria-label="Alterar status do sinistro"]').exists()).toBe(false);
        expect(wrapper.findAll('button').map((button) => button.text())).not.toContain('Editar');
    });
});
