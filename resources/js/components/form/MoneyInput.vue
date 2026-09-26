<script setup>
/*
 * Campo monetário em reais. O usuário digita no formato brasileiro
 * ("1.234,56"); o v-model contém sempre a string decimal esperada pela API
 * ("1234.56"), sem passar por number para não perder precisão.
 */
import { ref, watch } from 'vue';
import { formatMoneyInput, parseMoneyInput } from '@/utils/money';
import FormField from './FormField.vue';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [String, Number], default: '' });

defineProps({
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
});

const text = ref(formatMoneyInput(model.value));

watch(model, (value) => {
    if (parseMoneyInput(text.value) !== String(value ?? '')) {
        text.value = formatMoneyInput(value);
    }
});

function onInput(event) {
    text.value = event.target.value;
    model.value = parseMoneyInput(text.value);
}

function onBlur() {
    text.value = formatMoneyInput(model.value);
}
</script>

<template>
    <FormField v-slot="{ id, describedBy, invalid }" :label="label" :error="error" :hint="hint" :required="required">
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500" aria-hidden="true">R$</span>
            <input
                :id="id"
                :value="text"
                type="text"
                inputmode="decimal"
                autocomplete="off"
                :required="required"
                :aria-describedby="describedBy"
                :aria-invalid="invalid || undefined"
                class="form-control pl-10 text-right tabular-nums"
                v-bind="$attrs"
                @input="onInput"
                @blur="onBlur"
            />
        </div>
    </FormField>
</template>
