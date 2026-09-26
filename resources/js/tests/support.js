/*
 * Utilitários dos testes de páginas: monta um componente com Pinia e um router
 * em memória, com o usuário autenticado e as opções da API já carregadas,
 * reproduzindo o estado que o AppLayout garante em produção.
 */
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useOptionsStore } from '@/stores/options';

export const options = {
    person_types: [
        { value: 'individual', label: 'Pessoa física' },
        { value: 'company', label: 'Pessoa jurídica' },
    ],
    payment_methods: [{ value: 'pix', label: 'Pix' }],
    policy_statuses: [
        { value: 'quote', label: 'Cotação' },
        { value: 'active', label: 'Ativa' },
        { value: 'cancelled', label: 'Cancelada' },
    ],
    installment_statuses: [
        { value: 'pending', label: 'Pendente' },
        { value: 'overdue', label: 'Atrasado' },
    ],
    claim_statuses: [{ value: 'open', label: 'Aberto' }],
    user_roles: [{ value: 'broker', label: 'Corretor' }],
    activity_actions: [{ value: 'created', label: 'Criação' }],
    activity_subject_types: [{ value: 'policy', label: 'Apólice' }],
    field_labels: { premium: 'prêmio' },
    states: ['SP', 'RJ'],
};

export async function mountPage(component, { permissions = {}, route = '/', props = {}, routes = [] } = {}) {
    const pinia = createPinia();
    setActivePinia(pinia);
    useAuthStore().user = { id: 1, name: 'Ana', role: { value: 'admin', label: 'Administrador' }, permissions };
    useOptionsStore().options = options;
    useOptionsStore().loaded = true;

    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/:pathMatch(.*)*', name: 'any', component: { template: '<div />' } },
            ...routes,
        ],
    });
    await router.push(route);
    await router.isReady();

    const wrapper = mount(component, {
        props,
        global: {
            plugins: [pinia, router],
            stubs: { BarChart: true, Teleport: true },
        },
        attachTo: document.body,
    });
    await flushPromises();

    return { wrapper, router, pinia };
}
