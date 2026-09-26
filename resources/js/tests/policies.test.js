import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { activityLogsApi, installmentsApi, policiesApi } from '@/api';
import { ApiError } from '@/api/http';
import InstallmentTable from '@/components/installments/InstallmentTable.vue';
import PayInstallmentModal from '@/components/installments/PayInstallmentModal.vue';
import PolicyStatusActions from '@/components/policies/PolicyStatusActions.vue';
import PolicyDetailPage from '@/pages/policies/PolicyDetailPage.vue';
import { useConfirmStore } from '@/stores/confirm';
import { useNotificationsStore } from '@/stores/notifications';
import { mountPage } from './support';

vi.mock('@/api', () => ({
    policiesApi: { get: vi.fn(), changeStatus: vi.fn(), update: vi.fn(), remove: vi.fn(), createCoverage: vi.fn() },
    coveragesApi: { update: vi.fn(), remove: vi.fn() },
    installmentsApi: { pay: vi.fn() },
    activityLogsApi: { list: vi.fn() },
}));

const installment = (overrides = {}) => ({
    id: 11,
    policy_id: 7,
    number: 1,
    amount: '333.34',
    due_date: '2026-08-10',
    paid_on: null,
    status: { value: 'overdue', label: 'Atrasado' },
    is_overdue: true,
    is_payable: true,
    ...overrides,
});

const policy = (overrides = {}) => ({
    id: 7,
    number: '01.23.4567890',
    status: { value: 'active', label: 'Ativa' },
    allowed_transitions: [
        { value: 'suspended', label: 'Suspensa' },
        { value: 'cancelled', label: 'Cancelada' },
    ],
    is_editable: true,
    is_deletable: false,
    client_id: 3,
    insurer_id: 2,
    client: { id: 3, name: 'Maria Souza' },
    insurer: { id: 2, trade_name: 'Aurora Seguros' },
    product: { id: 1, name: 'Seguro Auto' },
    broker: { id: 4, name: 'Bruno' },
    starts_on: '2026-01-10',
    ends_on: '2027-01-10',
    premium: '1000.00',
    deductible: null,
    commission_rate: '12.50',
    commission_amount: '125.00',
    payment_method: { value: 'pix', label: 'Pix' },
    installments_count: 3,
    notes: null,
    coverages: [],
    installments: [installment(), installment({ id: 12, number: 2, status: { value: 'paid', label: 'Pago' }, is_overdue: false, is_payable: false })],
    claims: [],
    ...overrides,
});

beforeEach(() => {
    vi.clearAllMocks();
    activityLogsApi.list.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1 } });
});

describe('PolicyStatusActions', () => {
    it('offers only the transitions returned by the API and confirms before changing', async () => {
        policiesApi.changeStatus.mockResolvedValue(policy({ status: { value: 'suspended', label: 'Suspensa' } }));
        const { wrapper } = await mountPage(PolicyStatusActions, { props: { policy: policy() } });

        const labels = wrapper.findAll('button').map((button) => button.text());
        expect(labels).toEqual(['Marcar como suspensa', 'Cancelar apólice']);

        await wrapper.findAll('button')[0].trigger('click');
        expect(policiesApi.changeStatus).not.toHaveBeenCalled();

        useConfirmStore().answer(true);
        await flushPromises();

        expect(policiesApi.changeStatus).toHaveBeenCalledWith(7, 'suspended');
        expect(wrapper.emitted('changed')).toHaveLength(1);
    });

    it('shows the backend message when a transition is rejected', async () => {
        policiesApi.changeStatus.mockRejectedValue(new ApiError({ status: 422, message: 'A apólice só pode ser expirada após o fim da vigência.' }));
        const { wrapper } = await mountPage(PolicyStatusActions, {
            props: { policy: policy({ allowed_transitions: [{ value: 'expired', label: 'Expirada' }] }) },
        });

        await wrapper.find('button').trigger('click');
        useConfirmStore().answer(true);
        await flushPromises();

        expect(useNotificationsStore().items[0].message).toBe('A apólice só pode ser expirada após o fim da vigência.');
        expect(wrapper.emitted('changed')).toBeUndefined();
    });

    it('renders nothing for final policies', async () => {
        const { wrapper } = await mountPage(PolicyStatusActions, { props: { policy: policy({ allowed_transitions: [] }) } });

        expect(wrapper.find('button').exists()).toBe(false);
    });
});

describe('InstallmentTable', () => {
    it('shows the effective status from the API and the pay action only when allowed', async () => {
        const { wrapper } = await mountPage(InstallmentTable, {
            props: { rows: policy().installments },
            permissions: { 'installments.pay': true },
        });

        expect(wrapper.text()).toContain('Atrasado');
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Registrar pagamento')).toHaveLength(1);

        const withoutPermission = await mountPage(InstallmentTable, { props: { rows: policy().installments }, permissions: {} });
        expect(withoutPermission.wrapper.text()).not.toContain('Registrar pagamento');
    });
});

describe('PayInstallmentModal', () => {
    it('registers the payment and emits the paid installment', async () => {
        installmentsApi.pay.mockResolvedValue(installment({ status: { value: 'paid', label: 'Pago' }, is_payable: false }));
        const { wrapper } = await mountPage(PayInstallmentModal, { props: { installment: null } });

        await wrapper.setProps({ installment: installment() });
        await wrapper.find('input[type="date"]').setValue('2026-09-20');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(installmentsApi.pay).toHaveBeenCalledWith(11, '2026-09-20');
        expect(wrapper.emitted('paid')).toHaveLength(1);
    });

    it('keeps the modal open and shows validation errors on 422', async () => {
        installmentsApi.pay.mockRejectedValue(
            new ApiError({ status: 422, message: 'Os dados informados são inválidos.', errors: { paid_on: ['A data não pode ser futura.'] } }),
        );
        const { wrapper } = await mountPage(PayInstallmentModal, { props: { installment: installment() } });

        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('A data não pode ser futura.');
        expect(wrapper.emitted('paid')).toBeUndefined();
    });
});

describe('PolicyDetailPage', () => {
    const routes = [
        { path: '/policies/:id', name: 'policies.show', component: { template: '<div />' } },
        { path: '/policies/:id/edit', name: 'policies.edit', component: { template: '<div />' } },
        { path: '/policies', name: 'policies.index', component: { template: '<div />' } },
        { path: '/clients/:id', name: 'clients.show', component: { template: '<div />' } },
        { path: '/insurers/:id', name: 'insurers.show', component: { template: '<div />' } },
    ];

    it('derives available actions from API flags and user permissions', async () => {
        policiesApi.get.mockResolvedValue(policy());
        const { wrapper } = await mountPage(PolicyDetailPage, {
            props: { id: '7' },
            routes,
            permissions: { 'policies.manage': true, 'policies.delete': true, 'claims.manage': true },
        });
        const text = wrapper.text();

        expect(text).toContain('Apólice 01.23.4567890');
        expect(text).toContain('R$');
        expect(text).toContain('Editar');
        expect(text).toContain('Cancelar apólice');
        expect(text).toContain('Abrir sinistro');
        expect(text).not.toContain('Excluir');
    });

    it('shows a read-only view for final policies and users without permissions', async () => {
        policiesApi.get.mockResolvedValue(policy({ status: { value: 'cancelled', label: 'Cancelada' }, allowed_transitions: [], is_editable: false }));
        const { wrapper } = await mountPage(PolicyDetailPage, { props: { id: '7' }, routes, permissions: {} });
        const buttons = wrapper.findAll('a, button').map((element) => element.text());

        expect(buttons).not.toContain('Editar');
        expect(buttons).not.toContain('Abrir sinistro');
        expect(wrapper.text()).not.toContain('Cancelar apólice');
    });

    it('shows a not found state for missing policies', async () => {
        policiesApi.get.mockRejectedValue(new ApiError({ status: 404, message: 'Recurso não encontrado.' }));
        const { wrapper } = await mountPage(PolicyDetailPage, { props: { id: '999' }, routes });

        expect(wrapper.find('[role="alert"]').text()).toContain('Registro não encontrado');
        expect(wrapper.text()).not.toContain('Tentar novamente');
    });
});
