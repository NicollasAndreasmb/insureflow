import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fetchDashboard } from '@/api';
import { ApiError } from '@/api/http';
import DashboardPage from '@/pages/DashboardPage.vue';
import { mountPage } from './support';

vi.mock('@/api', () => ({ fetchDashboard: vi.fn() }));

const metrics = {
    generated_at: '2026-09-26T10:00:00-03:00',
    expiring_window_days: 30,
    clients: { total: 84, active: 80 },
    policies: { total: 180, active: 103, expiring_soon: 25, by_status: [{ value: 'active', label: 'Ativa', count: 103 }] },
    premiums: { active_total: '450865.09', active_commission_total: '72809.90' },
    installments: {
        pending: { label: 'Pendente', count: 161, amount: '168145.29' },
        overdue: { label: 'Atrasado', count: 24, amount: '21000.00' },
        paid: { label: 'Pago', count: 475, amount: '500000.00' },
        cancelled: { label: 'Cancelado', count: 13, amount: '1000.00' },
    },
    claims: { total: 45, open: 23, by_status: [{ value: 'open', label: 'Aberto', count: 5 }] },
    policies_by_product: [{ product_id: 1, name: 'Seguro Auto', policies_count: 76, active_count: 40, active_premium: '1.00' }],
    upcoming_expirations: [
        { id: 7, number: '01.23.4567890', client: { id: 3, name: 'Maria Souza' }, product: { id: 1, name: 'Seguro Auto' }, ends_on: '2026-10-01', days_left: 5, premium: '1000.00' },
    ],
};

beforeEach(() => fetchDashboard.mockReset());

describe('DashboardPage', () => {
    it('renders the aggregates returned by the API without recalculating them', async () => {
        fetchDashboard.mockResolvedValue(metrics);

        const { wrapper } = await mountPage(DashboardPage);
        const text = wrapper.text();

        expect(fetchDashboard).toHaveBeenCalledWith({ expiring_within_days: 30 });
        expect(text).toContain('103');
        expect(text).toContain('Vencendo em 30 dias');
        expect(text).toContain('25');
        expect(text).toContain('Maria Souza');
        expect(text).toContain('Em 5 dias');
        expect(text).toContain('01/10/2026');
        expect(text.replace(/\s/g, ' ')).toContain('R$ 450.865,09');
    });

    it('shows an error state with retry when the API fails', async () => {
        fetchDashboard.mockRejectedValueOnce(new ApiError({ status: 500, message: 'Ocorreu um erro interno.' }));

        const { wrapper } = await mountPage(DashboardPage);

        expect(wrapper.find('[role="alert"]').text()).toContain('Ocorreu um erro interno.');

        fetchDashboard.mockResolvedValue(metrics);
        await wrapper.find('[role="alert"] button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Maria Souza');
    });

    it('shows an empty state when there are no upcoming expirations', async () => {
        fetchDashboard.mockResolvedValue({ ...metrics, upcoming_expirations: [] });

        const { wrapper } = await mountPage(DashboardPage);

        expect(wrapper.text()).toContain('Nenhum vencimento no período');
    });
});
