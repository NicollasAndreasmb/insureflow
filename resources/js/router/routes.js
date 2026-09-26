/*
 * Mapa de rotas da SPA. As páginas são carregadas sob demanda (um chunk por
 * módulo). `meta.permission` referencia as chaves de /api/user.
 */
import AppLayout from '@/layouts/AppLayout.vue';

export const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/auth/LoginPage.vue'),
        meta: { guest: true, title: 'Entrar' },
    },
    {
        path: '/',
        component: AppLayout,
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'dashboard', component: () => import('@/pages/DashboardPage.vue'), meta: { title: 'Dashboard' } },

            { path: 'clients', name: 'clients.index', component: () => import('@/pages/clients/ClientListPage.vue'), meta: { title: 'Clientes' } },
            {
                path: 'clients/new',
                name: 'clients.create',
                component: () => import('@/pages/clients/ClientFormPage.vue'),
                meta: { title: 'Novo cliente', permission: 'clients.create' },
            },
            {
                path: 'clients/:id(\\d+)',
                name: 'clients.show',
                component: () => import('@/pages/clients/ClientDetailPage.vue'),
                props: true,
                meta: { title: 'Cliente' },
            },
            {
                path: 'clients/:id(\\d+)/edit',
                name: 'clients.edit',
                component: () => import('@/pages/clients/ClientFormPage.vue'),
                props: true,
                meta: { title: 'Editar cliente', permission: 'clients.update' },
            },

            { path: 'insurers', name: 'insurers.index', component: () => import('@/pages/insurers/InsurerListPage.vue'), meta: { title: 'Seguradoras' } },
            {
                path: 'insurers/new',
                name: 'insurers.create',
                component: () => import('@/pages/insurers/InsurerFormPage.vue'),
                meta: { title: 'Nova seguradora', permission: 'insurers.manage' },
            },
            {
                path: 'insurers/:id(\\d+)',
                name: 'insurers.show',
                component: () => import('@/pages/insurers/InsurerDetailPage.vue'),
                props: true,
                meta: { title: 'Seguradora' },
            },
            {
                path: 'insurers/:id(\\d+)/edit',
                name: 'insurers.edit',
                component: () => import('@/pages/insurers/InsurerFormPage.vue'),
                props: true,
                meta: { title: 'Editar seguradora', permission: 'insurers.manage' },
            },

            { path: 'policies', name: 'policies.index', component: () => import('@/pages/policies/PolicyListPage.vue'), meta: { title: 'Apólices' } },
            {
                path: 'policies/new',
                name: 'policies.create',
                component: () => import('@/pages/policies/PolicyFormPage.vue'),
                meta: { title: 'Nova apólice', permission: 'policies.manage' },
            },
            {
                path: 'policies/:id(\\d+)',
                name: 'policies.show',
                component: () => import('@/pages/policies/PolicyDetailPage.vue'),
                props: true,
                meta: { title: 'Apólice' },
            },
            {
                path: 'policies/:id(\\d+)/edit',
                name: 'policies.edit',
                component: () => import('@/pages/policies/PolicyFormPage.vue'),
                props: true,
                meta: { title: 'Editar apólice', permission: 'policies.manage' },
            },

            { path: 'insurance-products', name: 'products.index', component: () => import('@/pages/products/ProductListPage.vue'), meta: { title: 'Modalidades' } },

            { path: 'forbidden', name: 'forbidden', component: () => import('@/pages/errors/ForbiddenPage.vue'), meta: { title: 'Acesso negado' } },
            { path: ':pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/errors/NotFoundPage.vue'), meta: { title: 'Página não encontrada' } },
        ],
    },
];
