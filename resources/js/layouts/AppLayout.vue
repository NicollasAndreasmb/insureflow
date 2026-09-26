<script setup>
/*
 * Layout das áreas autenticadas: sidebar fixa no desktop e gaveta (drawer) no
 * mobile, header com identificação do usuário e logout, e a área principal onde
 * as páginas são renderizadas. As opções da API (/api/options) são carregadas
 * aqui, uma vez por sessão, antes de renderizar as páginas que dependem delas.
 */
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import SidebarNav from '@/components/layout/SidebarNav.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import { useOptionsStore } from '@/stores/options';

const auth = useAuthStore();
const options = useOptionsStore();
const notifications = useNotificationsStore();
const route = useRoute();
const router = useRouter();

const sidebarOpen = ref(false);
const optionsError = ref(null);
const loggingOut = ref(false);

async function loadOptions() {
    optionsError.value = null;

    try {
        await options.load();
    } catch (error) {
        optionsError.value = error;
    }
}

async function logout() {
    loggingOut.value = true;

    try {
        await auth.logout();
        await router.push({ name: 'login' });
    } catch (error) {
        notifications.error(error.message);
    } finally {
        loggingOut.value = false;
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        sidebarOpen.value = false;
    }
}

watch(() => route.fullPath, () => {
    sidebarOpen.value = false;
});

onMounted(() => {
    loadOptions();
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <a
            href="#main-content"
            class="sr-only z-[70] rounded-md bg-white px-3 py-2 text-sm font-medium text-brand-900 focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:shadow"
        >
            Pular para o conteúdo
        </a>

        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" aria-hidden="true" @click="sidebarOpen = false" />

        <aside
            id="app-sidebar"
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-brand-950 transition-transform lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            aria-label="Menu lateral"
        >
            <div class="flex h-16 items-center justify-between gap-2 border-b border-white/10 px-5">
                <RouterLink :to="{ name: 'dashboard' }" class="flex items-center gap-2 rounded focus-visible:outline-2 focus-visible:outline-accent-500">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-accent-500 text-white">
                        <AppIcon name="shield" :size="18" />
                    </span>
                    <span class="text-lg font-semibold text-white">InsureFlow</span>
                </RouterLink>
                <button
                    type="button"
                    class="rounded-md p-1 text-brand-100 hover:text-white focus-visible:outline-2 focus-visible:outline-accent-500 lg:hidden"
                    aria-label="Fechar menu"
                    @click="sidebarOpen = false"
                >
                    <AppIcon name="close" />
                </button>
            </div>
            <SidebarNav @navigate="sidebarOpen = false" />
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6">
                <button
                    type="button"
                    class="-ml-1 rounded-md p-1.5 text-slate-600 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-brand-600 lg:hidden"
                    aria-label="Abrir menu"
                    aria-controls="app-sidebar"
                    :aria-expanded="sidebarOpen"
                    @click="sidebarOpen = true"
                >
                    <AppIcon name="menu" />
                </button>

                <div class="ml-auto flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-medium text-slate-800">{{ auth.user?.name }}</p>
                        <p class="text-xs text-slate-500">{{ auth.user?.role?.label }}</p>
                    </div>
                    <span
                        class="flex size-9 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-800"
                        aria-hidden="true"
                    >
                        {{ auth.user?.name?.charAt(0) }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-brand-600 disabled:opacity-50"
                        :disabled="loggingOut"
                        @click="logout"
                    >
                        <AppIcon name="logout" :size="18" />
                        <span class="hidden sm:inline">Sair</span>
                        <span class="sr-only sm:hidden">Sair do sistema</span>
                    </button>
                </div>
            </header>

            <main id="main-content" tabindex="-1" class="mx-auto max-w-7xl px-4 py-6 focus:outline-none sm:px-6 lg:px-8">
                <ErrorState v-if="optionsError" :error="optionsError" @retry="loadOptions" />
                <LoadingState v-else-if="!options.loaded" />
                <RouterView v-else v-slot="{ Component, route: current }">
                    <component :is="Component" :key="`${String(current.name)}:${JSON.stringify(current.params)}`" />
                </RouterView>
            </main>
        </div>
    </div>
</template>
