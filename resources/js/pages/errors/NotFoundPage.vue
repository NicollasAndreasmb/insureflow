<script setup>
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
</script>

<template>
    <EmptyState title="Página não encontrada" description="O endereço acessado não existe ou foi removido." icon="search">
        <AppButton :to="{ name: 'dashboard' }" variant="secondary">Voltar ao dashboard</AppButton>
    </EmptyState>
</template>
