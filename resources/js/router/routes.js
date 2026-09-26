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

            { path: 'forbidden', name: 'forbidden', component: () => import('@/pages/errors/ForbiddenPage.vue'), meta: { title: 'Acesso negado' } },
            { path: ':pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/errors/NotFoundPage.vue'), meta: { title: 'Página não encontrada' } },
        ],
    },
];
