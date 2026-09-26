<script setup>
/*
 * Estado de erro de carregamento. Um 404 da API (registro inexistente ou
 * excluído) recebe título próprio e opção de voltar, em vez de "tentar
 * novamente", que não mudaria o resultado.
 */
import { computed } from 'vue';
import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    error: { type: Object, default: null },
    title: { type: String, default: '' },
    retryable: { type: Boolean, default: true },
});

defineEmits(['retry']);

const isNotFound = computed(() => props.error?.isNotFound === true);
const heading = computed(() => props.title || (isNotFound.value ? 'Registro não encontrado' : 'Não foi possível carregar os dados'));
const message = computed(() =>
    isNotFound.value ? 'O registro não existe ou foi excluído.' : props.error?.message,
);
</script>

<template>
    <div role="alert" class="flex flex-col items-center justify-center px-4 py-10 text-center">
        <div class="mb-3 rounded-full p-3" :class="isNotFound ? 'bg-slate-100 text-slate-400' : 'bg-red-50 text-red-500'">
            <AppIcon :name="isNotFound ? 'search' : 'alert'" :size="24" />
        </div>
        <p class="font-medium text-slate-800">{{ heading }}</p>
        <p v-if="message" class="mt-1 max-w-md text-sm text-slate-500">{{ message }}</p>
        <AppButton v-if="isNotFound" class="mt-4" variant="secondary" icon="chevronLeft" @click="$router.back()">Voltar</AppButton>
        <AppButton v-else-if="retryable" class="mt-4" variant="secondary" icon="refresh" @click="$emit('retry')">
            Tentar novamente
        </AppButton>
    </div>
</template>
