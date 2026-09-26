/*
 * Estado da sessão do usuário.
 *
 * A fonte da verdade é o backend: o usuário e suas permissões vêm sempre de
 * /api/user (ou da resposta do login), e nada é persistido no navegador — a
 * sessão vive no cookie HttpOnly do Laravel. initialize() é chamado pelo guard
 * do router antes da primeira navegação e reaproveita a mesma promessa, de modo
 * que o /api/user é consultado uma única vez por carregamento da página.
 */
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import * as authApi from '@/api/auth';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    let initialization = null;

    const isAuthenticated = computed(() => user.value !== null);
    const permissions = computed(() => user.value?.permissions ?? {});

    function can(permission) {
        return permissions.value[permission] === true;
    }

    function initialize() {
        initialization ??= authApi
            .fetchCurrentUser()
            .then((currentUser) => {
                user.value = currentUser;
            })
            .catch(() => {
                user.value = null;
            });

        return initialization;
    }

    async function login(credentials) {
        user.value = await authApi.login(credentials);
        initialization = Promise.resolve();
    }

    async function logout() {
        try {
            await authApi.logout();
        } finally {
            clear();
        }
    }

    function clear() {
        user.value = null;
        initialization = Promise.resolve();
    }

    return { user, isAuthenticated, permissions, can, initialize, login, logout, clear };
});
