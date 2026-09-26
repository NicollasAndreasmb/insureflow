/*
 * Rotas da SPA. As páginas são carregadas sob demanda (lazy loading) para que
 * cada módulo gere seu próprio chunk. Os guards de autenticação serão
 * adicionados aqui quando o módulo de login for implementado.
 */
import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/',
        name: 'home',
        component: () => import('@/pages/HomePage.vue'),
        meta: { title: 'Início' },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/pages/NotFoundPage.vue'),
        meta: { title: 'Página não encontrada' },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.afterEach((to) => {
    const appName = import.meta.env.VITE_APP_NAME ?? 'InsureFlow';
    document.title = to.meta.title ? `${to.meta.title} · ${appName}` : appName;
});

export default router;
