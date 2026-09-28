import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { clientsApi } from '@/api';
import { ApiError } from '@/api/http';
import ClientFormPage from '@/pages/clients/ClientFormPage.vue';
import ClientListPage from '@/pages/clients/ClientListPage.vue';
import { mountPage } from './support';

vi.mock('@/api', () => ({
    clientsApi: { list: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}));

const page = (data, total = data.length) => ({ data, meta: { current_page: 1, last_page: 1, from: 1, to: data.length, total } });

const clientRow = {
    id: 5,
    name: 'Maria Souza',
    document: '52998224725',
    person_type: { value: 'individual', label: 'Pessoa física' },
    city: 'Campinas',
    state: 'SP',
    email: 'maria@example.com',
    mobile: '11987654321',
    policies_count: 2,
    is_active: true,
};

const clientRoutes = [
    { path: '/clients', name: 'clients.index', component: { template: '<div />' } },
    { path: '/clients/new', name: 'clients.create', component: { template: '<div />' } },
    { path: '/clients/:id', name: 'clients.show', component: { template: '<div />' } },
];

beforeEach(() => vi.clearAllMocks());

describe('ClientListPage', () => {
    it('sends URL filters and sort to the API and renders the rows', async () => {
        clientsApi.list.mockResolvedValue(page([clientRow]));

        const { wrapper } = await mountPage(ClientListPage, {
            route: '/clients?search=maria&person_type=individual',
            routes: clientRoutes,
            permissions: { 'clients.create': true },
        });

        expect(clientsApi.list).toHaveBeenCalledWith(
            expect.objectContaining({ search: 'maria', person_type: 'individual', sort: 'name', page: 1 }),
            expect.anything(),
        );
        expect(wrapper.text()).toContain('Maria Souza');
        expect(wrapper.text()).toContain('529.982.247-25');
        expect(wrapper.text()).toContain('Novo cliente');
    });

    it('requests the new sort when a sortable header is clicked', async () => {
        clientsApi.list.mockResolvedValue(page([clientRow]));
        const { wrapper, router } = await mountPage(ClientListPage, { route: '/clients', routes: clientRoutes });

        await wrapper.find('th button').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.query.sort).toBe('-name');
        expect(clientsApi.list).toHaveBeenLastCalledWith(expect.objectContaining({ sort: '-name' }), expect.anything());
    });

    it('hides creation when the backend does not grant it and shows an empty state', async () => {
        clientsApi.list.mockResolvedValue(page([]));

        const { wrapper } = await mountPage(ClientListPage, { route: '/clients', routes: clientRoutes, permissions: {} });

        expect(wrapper.text()).not.toContain('Novo cliente');
        expect(wrapper.text()).toContain('Nenhum cliente cadastrado');
    });
});

describe('ClientFormPage', () => {
    it('submits digits only and shows 422 errors next to each field', async () => {
        clientsApi.create.mockRejectedValue(
            new ApiError({ status: 422, message: 'Os dados informados são inválidos.', errors: { document: ['O CPF informado é inválido.'] } }),
        );
        const { wrapper } = await mountPage(ClientFormPage, { route: '/clients/new', routes: clientRoutes });

        await wrapper.find('input[type="text"]').setValue('Maria Souza');
        const documentInput = wrapper.findAll('input[inputmode="numeric"]')[0];
        await documentInput.setValue('529.982.247-25');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(clientsApi.create).toHaveBeenCalledWith(expect.objectContaining({ name: 'Maria Souza', document: '52998224725', email: null }));
        expect(wrapper.text()).toContain('O CPF informado é inválido.');
        expect(documentInput.attributes('aria-invalid')).toBe('true');
        expect(wrapper.find('[role="alert"]').text()).toContain('Os dados informados são inválidos.');
    });

    it('navigates to the client after creating it', async () => {
        clientsApi.create.mockResolvedValue({ id: 42 });
        const { wrapper, router } = await mountPage(ClientFormPage, { route: '/clients/new', routes: clientRoutes });

        await wrapper.find('input[type="text"]').setValue('Maria Souza');
        await wrapper.findAll('input[inputmode="numeric"]')[0].setValue('529.982.247-25');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(router.currentRoute.value.fullPath).toBe('/clients/42');
    });

    it('validates in the browser and does not call the API when fields are invalid', async () => {
        const { wrapper } = await mountPage(ClientFormPage, { route: '/clients/new', routes: clientRoutes });

        await wrapper.findAll('input[inputmode="numeric"]')[0].setValue('111.111.111-11');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(clientsApi.create).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Este campo é obrigatório.');
        expect(wrapper.text()).toContain('Informe um CPF válido.');
        expect(wrapper.find('[role="alert"]').text()).toContain('Corrija os campos destacados');
    });
});
