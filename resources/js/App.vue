<script setup>
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import ToastStack from '@/components/ui/ToastStack.vue';
</script>

<template>
    <RouterView />
    <ConfirmDialog />
    <ToastStack />
</template>
