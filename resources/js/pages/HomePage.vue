<script setup>
/*
 * Página temporária de verificação do ambiente (Fase 1).
 *
 * Confirma de ponta a ponta que o Vue foi carregado pelo Vite, que a API Laravel
 * responde através do nginx e que o PostgreSQL está acessível. Será substituída
 * pelo dashboard quando os módulos de negócio forem implementados.
 */
import { computed, onMounted, ref } from 'vue';
import { fetchHealth } from '@/services/healthService';

const health = ref(null);
const loading = ref(false);
const errorMessage = ref('');

const checks = computed(() => [
    { label: 'Frontend (Vue 3 + Vite)', ok: true, detail: 'Carregado' },
    {
        label: 'API Laravel',
        ok: health.value !== null,
        detail: health.value ? `Ambiente ${health.value.environment}` : 'Sem resposta',
    },
    {
        label: 'Banco de dados PostgreSQL',
        ok: health.value?.database === 'connected',
        detail: health.value?.database === 'connected' ? 'Conectado' : 'Indisponível',
    },
]);

async function checkHealth() {
    loading.value = true;
    errorMessage.value = '';

    try {
        health.value = await fetchHealth();
    } catch (error) {
        health.value = error.response?.data ?? null;
        errorMessage.value = error.response
            ? `A API respondeu com status ${error.response.status}.`
            : 'Não foi possível conectar à API.';
    } finally {
        loading.value = false;
    }
}

onMounted(checkHealth);
</script>

<template>
    <main class="flex min-h-screen items-center justify-center p-4">
        <section class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <header class="mb-6">
                <p class="text-sm font-semibold tracking-wide text-accent-600 uppercase">Gestão de seguros</p>
                <h1 class="mt-1 text-3xl font-semibold text-brand-950">InsureFlow</h1>
                <p class="mt-2 text-slate-500">Verificação do ambiente de desenvolvimento.</p>
            </header>

            <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                <li
                    v-for="check in checks"
                    :key="check.label"
                    class="flex items-center justify-between gap-4 px-4 py-3"
                >
                    <span class="font-medium text-slate-700">{{ check.label }}</span>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-sm font-medium"
                        :class="loading
                            ? 'bg-slate-100 text-slate-500'
                            : check.ok ? 'bg-accent-50 text-accent-700' : 'bg-red-50 text-red-700'"
                    >
                        {{ loading ? 'Verificando…' : check.detail }}
                    </span>
                </li>
            </ul>

            <p v-if="errorMessage" class="mt-4 text-sm text-red-600">{{ errorMessage }}</p>

            <button
                type="button"
                class="mt-6 w-full rounded-lg bg-brand-900 px-4 py-2.5 font-medium text-white transition hover:bg-brand-800 disabled:opacity-60"
                :disabled="loading"
                @click="checkHealth"
            >
                Verificar novamente
            </button>
        </section>
    </main>
</template>
