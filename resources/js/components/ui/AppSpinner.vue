<script setup>
defineProps({
    size: { type: Number, default: 16 },
});
</script>

<template>
    <svg
        class="animate-spin motion-reduce:animate-none"
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
    >
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" />
        <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
    </svg>
</template>
