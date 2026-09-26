<script setup>
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
</script>

<template>
    <EmptyState
        title="Acesso negado"
        description="Seu perfil não tem permissão para acessar esta área. Se precisar desse acesso, procure um administrador."
        icon="shield"
    >
        <AppButton :to="{ name: 'dashboard' }" variant="secondary">Voltar ao dashboard</AppButton>
    </EmptyState>
</template>
