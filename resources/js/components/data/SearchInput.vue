<script setup>
/*
 * Busca com debounce: o v-model só é atualizado 400ms após o usuário parar de
 * digitar (ou imediatamente com Enter), evitando uma requisição por tecla.
 */
import { onBeforeUnmount, ref, useId, watch } from 'vue';
import AppIcon from '../ui/AppIcon.vue';

const model = defineModel({ type: String, default: '' });

defineProps({
    label: { type: String, default: 'Buscar' },
    placeholder: { type: String, default: 'Buscar…' },
});

const id = useId();
const text = ref(model.value);
let timer = null;

watch(model, (value) => {
    text.value = value;
});

function commit() {
    clearTimeout(timer);

    if (text.value !== model.value) {
        model.value = text.value;
    }
}

function onInput(event) {
    text.value = event.target.value;
    clearTimeout(timer);
    timer = setTimeout(commit, 400);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div>
        <label :for="id" class="sr-only">{{ label }}</label>
        <div class="relative">
            <AppIcon name="search" :size="16" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400" />
            <input
                :id="id"
                :value="text"
                type="search"
                :placeholder="placeholder"
                class="form-control pl-9"
                @input="onInput"
                @keydown.enter.prevent="commit"
            />
        </div>
    </div>
</template>
