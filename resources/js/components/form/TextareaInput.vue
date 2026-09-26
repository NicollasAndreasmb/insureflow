<script setup>
import FormField from './FormField.vue';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: String, default: '' });

defineProps({
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    rows: { type: Number, default: 3 },
});
</script>

<template>
    <FormField v-slot="{ id, describedBy, invalid }" :label="label" :error="error" :hint="hint" :required="required">
        <textarea
            :id="id"
            v-model="model"
            :rows="rows"
            :required="required"
            :aria-describedby="describedBy"
            :aria-invalid="invalid || undefined"
            class="form-control"
            v-bind="$attrs"
        />
    </FormField>
</template>
