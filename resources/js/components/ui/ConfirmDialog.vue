<script setup>
import { computed } from 'vue';
import { useConfirmStore } from '@/stores/confirm';
import AppButton from './AppButton.vue';
import AppModal from './AppModal.vue';

const confirm = useConfirmStore();
const dialog = computed(() => confirm.dialog);
</script>

<template>
    <AppModal :open="dialog !== null" :title="dialog?.title ?? ''" size="sm" @close="confirm.answer(false)">
        <p class="text-sm text-slate-600">{{ dialog?.message }}</p>
        <template #footer>
            <AppButton variant="secondary" @click="confirm.answer(false)">Voltar</AppButton>
            <AppButton :variant="dialog?.danger ? 'danger' : 'primary'" autofocus @click="confirm.answer(true)">
                {{ dialog?.confirmLabel }}
            </AppButton>
        </template>
    </AppModal>
</template>
