<script setup>
/*
 * Envoltório comum dos campos: rótulo ligado ao controle (for/id), dica e
 * mensagem de erro associadas via aria-describedby, e aria-invalid quando há
 * erro. Os inputs recebem esses atributos pelo slot, garantindo que leitores de
 * tela anunciem a mensagem de validação vinda da API.
 */
import { computed, useId } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    id: { type: String, default: '' },
});

const generatedId = useId();
const fieldId = computed(() => props.id || generatedId);
const hintId = computed(() => `${fieldId.value}-hint`);
const errorId = computed(() => `${fieldId.value}-error`);

const describedBy = computed(
    () => [props.hint ? hintId.value : null, props.error ? errorId.value : null].filter(Boolean).join(' ') || undefined,
);
</script>

<template>
    <div>
        <label v-if="label" :for="fieldId" class="mb-1 block text-sm font-medium text-slate-700">
            {{ label }}
            <span v-if="required" class="text-red-600" aria-hidden="true">*</span>
        </label>
        <slot :id="fieldId" :described-by="describedBy" :invalid="Boolean(error)" />
        <p v-if="hint && !error" :id="hintId" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
        <p v-if="error" :id="errorId" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
