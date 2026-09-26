<script setup>
import FormField from './FormField.vue';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [String, Number], default: '' });

defineProps({
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    type: { type: String, default: 'text' },
});
</script>

<template>
    <FormField v-slot="{ id, describedBy, invalid }" :label="label" :error="error" :hint="hint" :required="required">
        <input
            :id="id"
            v-model="model"
            :type="type"
            :required="required"
            :aria-describedby="describedBy"
            :aria-invalid="invalid || undefined"
            class="form-control"
            v-bind="$attrs"
        />
    </FormField>
</template>
