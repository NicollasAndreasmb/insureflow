/*
 * Ponto de entrada da SPA. Além de montar Vue, Pinia e Router, conecta o
 * cliente HTTP ao fluxo de sessão: quando a API responde 401 durante o uso
 * (sessão expirada no servidor), o estado local é limpo e o usuário volta ao
 * login, retornando à mesma página depois de entrar novamente.
 */
import { createPinia } from 'pinia';
import { createApp } from 'vue';
import { onUnauthenticated } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import App from './App.vue';
import router from './router';

const pinia = createPinia();

onUnauthenticated(() => {
    const auth = useAuthStore(pinia);

    if (!auth.isAuthenticated) {
        return;
    }

    auth.clear();
    useNotificationsStore(pinia).info('Sua sessão expirou. Entre novamente para continuar.');
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
});

createApp(App).use(pinia).use(router).mount('#app');
