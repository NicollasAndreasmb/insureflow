<script setup>
import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';

defineProps({
    error: { type: Object, default: null },
    title: { type: String, default: 'Não foi possível carregar os dados' },
    retryable: { type: Boolean, default: true },
});

defineEmits(['retry']);
</script>

<template>
    <div role="alert" class="flex flex-col items-center justify-center px-4 py-10 text-center">
        <div class="mb-3 rounded-full bg-red-50 p-3 text-red-500">
            <AppIcon name="alert" :size="24" />
        </div>
        <p class="font-medium text-slate-800">{{ title }}</p>
        <p v-if="error?.message" class="mt-1 max-w-md text-sm text-slate-500">{{ error.message }}</p>
        <AppButton v-if="retryable" class="mt-4" variant="secondary" icon="refresh" @click="$emit('retry')">
            Tentar novamente
        </AppButton>
    </div>
</template>
