/*
 * Proteção de rotas.
 *
 * Antes da primeira navegação o estado da sessão é obtido de /api/user
 * (auth.initialize()). Rotas sob o layout autenticado exigem login; quem não
 * está autenticado vai para /login com ?redirect= para voltar ao destino após
 * entrar. `meta.permission` usa as chaves de permissão do backend — é só uma
 * camada de experiência: a API continua autorizando cada requisição.
 */
import { useAuthStore } from '@/stores/auth';

const APP_NAME = 'InsureFlow';

export function safeRedirect(value) {
    return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') ? value : null;
}

export function installGuards(router) {
    router.beforeEach(async (to) => {
        const auth = useAuthStore();
        await auth.initialize();

        if (to.meta.guest) {
            return auth.isAuthenticated ? { name: 'dashboard' } : true;
        }

        if (!to.matched.some((record) => record.meta.requiresAuth)) {
            return true;
        }

        if (!auth.isAuthenticated) {
            return { name: 'login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } };
        }

        if (to.meta.permission && !auth.can(to.meta.permission)) {
            return { name: 'forbidden', replace: true };
        }

        return true;
    });

    router.afterEach((to) => {
        document.title = to.meta.title ? `${to.meta.title} · ${APP_NAME}` : APP_NAME;
    });
}
