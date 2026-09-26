<script setup>
import AppIcon from '../ui/AppIcon.vue';

defineProps({
    message: { type: String, default: '' },
});
</script>

<template>
    <div v-if="message" role="alert" class="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-800">
        <AppIcon name="alert" class="mt-0.5 shrink-0" :size="18" />
        <p>{{ message }}</p>
    </div>
</template>
