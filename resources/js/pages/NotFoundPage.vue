<template>
    <main class="flex min-h-screen flex-col items-center justify-center gap-4 p-4 text-center">
        <p class="text-6xl font-semibold text-brand-900">404</p>
        <p class="text-slate-500">A página que você procura não existe.</p>
        <RouterLink :to="{ name: 'home' }" class="font-medium text-brand-600 hover:underline">
            Voltar ao início
        </RouterLink>
    </main>
</template>
