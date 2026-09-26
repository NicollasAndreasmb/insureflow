<script setup>
/*
 * Login da SPA. Usa o fluxo Sanctum (cookie CSRF + sessão) via auth store;
 * credenciais inválidas, conta inativa e excesso de tentativas chegam da API
 * como mensagens prontas e são exibidas sem tradução adicional.
 */
import { useRoute, useRouter } from 'vue-router';
import CheckboxInput from '@/components/form/CheckboxInput.vue';
import FormAlert from '@/components/form/FormAlert.vue';
import TextInput from '@/components/form/TextInput.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { useForm } from '@/composables/useForm';
import { safeRedirect } from '@/router/guards';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = useForm({ email: '', password: '', remember: false });

async function submit() {
    const success = await form.submit(async (fields) => {
        await auth.login(fields);

        return true;
    });

    if (success) {
        await router.replace(safeRedirect(route.query.redirect) ?? { name: 'dashboard' });
    }
}
</script>

<template>
    <div class="flex min-h-screen bg-white">
        <aside class="relative hidden w-[44%] flex-col justify-between overflow-hidden bg-brand-950 p-12 text-white lg:flex">
            <div class="flex items-center gap-2">
                <span class="flex size-9 items-center justify-center rounded-lg bg-accent-500">
                    <AppIcon name="shield" :size="20" />
                </span>
                <span class="text-xl font-semibold">InsureFlow</span>
            </div>
            <div class="max-w-md">
                <h2 class="text-3xl leading-tight font-semibold">A carteira da sua corretora organizada em um só lugar.</h2>
                <ul class="mt-8 space-y-4 text-brand-100">
                    <li class="flex gap-3"><AppIcon name="shield" class="shrink-0 text-accent-500" />Apólices, coberturas e vigências sob controle.</li>
                    <li class="flex gap-3"><AppIcon name="cash" class="shrink-0 text-accent-500" />Parcelas em atraso identificadas automaticamente.</li>
                    <li class="flex gap-3"><AppIcon name="alert" class="shrink-0 text-accent-500" />Sinistros acompanhados com histórico completo.</li>
                </ul>
            </div>
            <p class="text-sm text-brand-200/70">Gestão de seguros para corretoras.</p>
        </aside>

        <main class="flex flex-1 items-center justify-center px-4 py-12 sm:px-8">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex items-center gap-2 lg:hidden">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-brand-950 text-white">
                        <AppIcon name="shield" :size="20" />
                    </span>
                    <span class="text-xl font-semibold text-brand-950">InsureFlow</span>
                </div>

                <h1 class="text-2xl font-semibold text-brand-950">Entrar</h1>
                <p class="mt-1 text-sm text-slate-500">Acesse com o e-mail e a senha fornecidos pelo administrador.</p>

                <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
                    <FormAlert :message="form.generalError" />
                    <TextInput
                        v-model="form.fields.email"
                        label="E-mail"
                        type="email"
                        autocomplete="username"
                        autofocus
                        required
                        :error="form.error('email')"
                    />
                    <TextInput
                        v-model="form.fields.password"
                        label="Senha"
                        type="password"
                        autocomplete="current-password"
                        required
                        :error="form.error('password')"
                    />
                    <CheckboxInput v-model="form.fields.remember" label="Manter conectado neste dispositivo" />
                    <AppButton type="submit" class="w-full" :loading="form.processing">Entrar</AppButton>
                </form>
            </div>
        </main>
    </div>
</template>
