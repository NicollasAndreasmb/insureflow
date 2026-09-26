<script setup>
/*
 * Campo com máscara de digitação (CPF, CNPJ, telefone, CEP). A máscara é só
 * visual: o v-model recebe apenas os dígitos, formato que a API grava.
 */
import { computed } from 'vue';
import { applyMask, digitsOnly, MASKS } from '@/utils/masks';
import FormField from './FormField.vue';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: String, default: '' });

const props = defineProps({
    mask: { type: String, required: true },
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
});

const display = computed(() => applyMask(model.value ?? '', props.mask));

function onInput(event) {
    const digits = digitsOnly(event.target.value).slice(0, MASKS[props.mask].maxDigits);
    model.value = digits;
    event.target.value = applyMask(digits, props.mask);
}
</script>

<template>
    <FormField v-slot="{ id, describedBy, invalid }" :label="label" :error="error" :hint="hint" :required="required">
        <input
            :id="id"
            :value="display"
            type="text"
            inputmode="numeric"
            :required="required"
            :aria-describedby="describedBy"
            :aria-invalid="invalid || undefined"
            class="form-control tabular-nums"
            v-bind="$attrs"
            @input="onInput"
        />
    </FormField>
</template>
