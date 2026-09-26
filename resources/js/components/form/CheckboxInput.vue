<script setup>
import { useId } from 'vue';

const model = defineModel({ type: Boolean, default: false });

defineProps({
    label: { type: String, required: true },
    description: { type: String, default: '' },
    error: { type: String, default: '' },
});

const id = useId();
</script>

<template>
    <div>
        <div class="flex items-start gap-2.5">
            <input
                :id="id"
                v-model="model"
                type="checkbox"
                class="mt-0.5 size-4 rounded border-slate-300 text-brand-700 accent-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                :aria-describedby="error ? `${id}-error` : undefined"
            />
            <label :for="id" class="text-sm">
                <span class="font-medium text-slate-700">{{ label }}</span>
                <span v-if="description" class="block text-xs text-slate-500">{{ description }}</span>
            </label>
        </div>
        <p v-if="error" :id="`${id}-error`" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
