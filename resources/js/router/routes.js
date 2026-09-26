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
            { path: 'forbidden', name: 'forbidden', component: () => import('@/pages/errors/ForbiddenPage.vue'), meta: { title: 'Acesso negado' } },
            { path: ':pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/errors/NotFoundPage.vue'), meta: { title: 'Página não encontrada' } },
        ],
    },
];
