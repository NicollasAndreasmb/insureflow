import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { productsApi } from '@/api';
import { ApiError } from '@/api/http';
import ProductListPage from '@/pages/products/ProductListPage.vue';
import { useConfirmStore } from '@/stores/confirm';
import { useNotificationsStore } from '@/stores/notifications';
import { mountPage } from './support';

vi.mock('@/api', () => ({
    productsApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}));

const product = { id: 3, name: 'Seguro Auto', slug: 'seguro-auto', description: 'Veículos', policies_count: 12, is_active: true };
const page = { data: [product], meta: { current_page: 1, last_page: 1, from: 1, to: 1, total: 1 } };

beforeEach(() => {
    vi.clearAllMocks();
    productsApi.list.mockResolvedValue(page);
});

describe('ProductListPage', () => {
    it('shows management actions only with the backend permission', async () => {
        const reader = await mountPage(ProductListPage, { permissions: {} });
        expect(reader.wrapper.text()).not.toContain('Nova modalidade');
        expect(reader.wrapper.find('[aria-label="Excluir Seguro Auto"]').exists()).toBe(false);

        const admin = await mountPage(ProductListPage, { permissions: { 'insurance_products.manage': true } });
        expect(admin.wrapper.text()).toContain('Nova modalidade');
        expect(admin.wrapper.find('[aria-label="Excluir Seguro Auto"]').exists()).toBe(true);
    });

    it('creates a product through the modal and shows validation errors', async () => {
        productsApi.create.mockRejectedValueOnce(
            new ApiError({ status: 422, message: 'Os dados informados são inválidos.', errors: { slug: ['Este identificador já está em uso.'] } }),
        );
        const { wrapper } = await mountPage(ProductListPage, { permissions: { 'insurance_products.manage': true } });

        await wrapper.findAll('button').find((button) => button.text() === 'Nova modalidade').trigger('click');
        await wrapper.find('#product-form input[type="text"]').setValue('Seguro Auto');
        await wrapper.find('#product-form').trigger('submit');
        await flushPromises();

        expect(productsApi.create).toHaveBeenCalledWith({ name: 'Seguro Auto', description: '', is_active: true });
        expect(wrapper.text()).toContain('Este identificador já está em uso.');

        productsApi.create.mockResolvedValueOnce({ id: 9 });
        await wrapper.find('#product-form').trigger('submit');
        await flushPromises();

        expect(useNotificationsStore().items.map((item) => item.message)).toContain('Modalidade cadastrada.');
        expect(productsApi.list).toHaveBeenCalledTimes(2);
    });

    it('asks for confirmation before deleting and reports business rule errors', async () => {
        productsApi.remove.mockRejectedValue(
            new ApiError({ status: 422, message: 'A modalidade possui apólices em andamento. Inative-a em vez de excluí-la.' }),
        );
        const { wrapper } = await mountPage(ProductListPage, { permissions: { 'insurance_products.manage': true } });
        const confirm = useConfirmStore();

        await wrapper.find('[aria-label="Excluir Seguro Auto"]').trigger('click');
        expect(confirm.dialog.title).toBe('Excluir modalidade');
        expect(productsApi.remove).not.toHaveBeenCalled();

        confirm.answer(true);
        await flushPromises();

        expect(productsApi.remove).toHaveBeenCalledWith(3);
        expect(useNotificationsStore().items.map((item) => item.message)).toContain(
            'A modalidade possui apólices em andamento. Inative-a em vez de excluí-la.',
        );
    });
});
