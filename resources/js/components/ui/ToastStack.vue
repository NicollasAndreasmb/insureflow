<script setup>
import { useNotificationsStore } from '@/stores/notifications';
import AppIcon from './AppIcon.vue';

const notifications = useNotificationsStore();

const styles = {
    success: { icon: 'check', classes: 'border-accent-500 text-accent-700' },
    error: { icon: 'alert', classes: 'border-red-500 text-red-700' },
    info: { icon: 'info', classes: 'border-brand-500 text-brand-700' },
};
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end"
        aria-live="polite"
        aria-atomic="false"
    >
        <div
            v-for="item in notifications.items"
            :key="item.id"
            :role="item.type === 'error' ? 'alert' : 'status'"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border-l-4 bg-white px-4 py-3 shadow-lg ring-1 ring-slate-900/5"
            :class="styles[item.type].classes"
        >
            <AppIcon :name="styles[item.type].icon" class="mt-0.5 shrink-0" />
            <p class="flex-1 text-sm text-slate-700">{{ item.message }}</p>
            <button
                type="button"
                class="rounded p-0.5 text-slate-500 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-brand-600"
                aria-label="Fechar notificação"
                @click="notifications.dismiss(item.id)"
            >
                <AppIcon name="close" :size="16" />
            </button>
        </div>
    </div>
</template>
