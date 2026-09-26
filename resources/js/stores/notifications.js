import { defineStore } from 'pinia';
import { ref } from 'vue';

const AUTO_DISMISS_MS = 5000;

export const useNotificationsStore = defineStore('notifications', () => {
    const items = ref([]);
    let nextId = 1;

    function push(type, message) {
        const id = nextId++;
        items.value.push({ id, type, message });
        setTimeout(() => dismiss(id), AUTO_DISMISS_MS);

        return id;
    }

    function dismiss(id) {
        items.value = items.value.filter((item) => item.id !== id);
    }

    return {
        items,
        dismiss,
        success: (message) => push('success', message),
        error: (message) => push('error', message),
        info: (message) => push('info', message),
    };
});
