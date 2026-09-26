<script setup>
import FormField from './FormField.vue';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [String, Number, Boolean], default: '' });

defineProps({
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
});
</script>

<template>
    <FormField v-slot="{ id, describedBy, invalid }" :label="label" :error="error" :hint="hint" :required="required">
        <select
            :id="id"
            v-model="model"
            :required="required"
            :aria-describedby="describedBy"
            :aria-invalid="invalid || undefined"
            class="form-control pr-8"
            v-bind="$attrs"
        >
            <option v-if="placeholder" value="">{{ placeholder }}</option>
            <option v-for="option in options" :key="String(option.value)" :value="option.value">
                {{ option.label }}
            </option>
        </select>
    </FormField>
</template>
