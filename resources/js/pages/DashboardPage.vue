<script setup>
import PageHeader from '@/components/ui/PageHeader.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
</script>

<template>
    <PageHeader title="Dashboard" :subtitle="`Olá, ${auth.user?.name}.`" />
</template>
