<script setup>
/*
 * Seleção de um registro buscando na API conforme o usuário digita (ex.:
 * cliente da apólice, apólice do sinistro). Evita carregar listas inteiras e
 * reaproveita o filtro `search` que a API já oferece.
 *
 * Segue o padrão ARIA de combobox: input com role="combobox", lista com
 * role="listbox", aria-activedescendant para a opção destacada, setas para
 * navegar, Enter para escolher e Esc para fechar. O v-model guarda só o id.
 */
import { onBeforeUnmount, ref, useId, watch } from 'vue';
import AppSpinner from '../ui/AppSpinner.vue';
import FormField from './FormField.vue';

const model = defineModel({ type: [Number, String], default: null });

const props = defineProps({
    fetcher: { type: Function, required: true },
    selected: { type: Object, default: null },
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Digite para buscar…' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

const listId = useId();
const query = ref(props.selected?.label ?? '');
const results = ref([]);
const open = ref(false);
const loading = ref(false);
const activeIndex = ref(-1);
let debounceTimer = null;
let requestId = 0;

watch(
    () => props.selected,
    (option) => {
        if (option) {
            query.value = option.label;
        }
    },
);

async function search() {
    const current = ++requestId;
    loading.value = true;

    try {
        const options = await props.fetcher(query.value.trim());

        if (current === requestId) {
            results.value = options;
            activeIndex.value = options.length ? 0 : -1;
        }
    } catch {
        if (current === requestId) {
            results.value = [];
        }
    } finally {
        if (current === requestId) {
            loading.value = false;
        }
    }
}

function scheduleSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(search, 300);
}

function onInput(event) {
    query.value = event.target.value;
    model.value = null;
    open.value = true;
    scheduleSearch();
}

function onFocus() {
    open.value = true;

    if (!results.value.length) {
        search();
    }
}

function choose(option) {
    model.value = option.value;
    query.value = option.label;
    open.value = false;
    emit('select', option);
}

function onKeydown(event) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        open.value = true;

        if (results.value.length) {
            const step = event.key === 'ArrowDown' ? 1 : -1;
            activeIndex.value = (activeIndex.value + step + results.value.length) % results.value.length;
        }
    } else if (event.key === 'Enter' && open.value && results.value[activeIndex.value]) {
        event.preventDefault();
        choose(results.value[activeIndex.value]);
    } else if (event.key === 'Escape') {
        open.value = false;
    }
}

function onBlur() {
    setTimeout(() => {
        open.value = false;
    }, 150);
}

onBeforeUnmount(() => clearTimeout(debounceTimer));
</script>

<template>
    <FormField v-slot="{ id, describedBy, invalid }" :label="label" :error="error" :hint="hint" :required="required">
        <div class="relative">
            <input
                :id="id"
                :value="query"
                type="text"
                role="combobox"
                autocomplete="off"
                aria-autocomplete="list"
                :aria-expanded="open"
                :aria-controls="listId"
                :aria-activedescendant="open && activeIndex >= 0 ? `${listId}-${activeIndex}` : undefined"
                :aria-describedby="describedBy"
                :aria-invalid="invalid || undefined"
                :placeholder="placeholder"
                :disabled="disabled"
                class="form-control pr-8"
                @input="onInput"
                @focus="onFocus"
                @blur="onBlur"
                @keydown="onKeydown"
            />
            <AppSpinner v-if="loading" class="absolute top-1/2 right-2.5 -translate-y-1/2 text-slate-400" />
            <ul
                v-show="open"
                :id="listId"
                role="listbox"
                class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
            >
                <li
                    v-for="(option, index) in results"
                    :id="`${listId}-${index}`"
                    :key="option.value"
                    role="option"
                    :aria-selected="option.value === model"
                    class="cursor-pointer px-3 py-2 text-sm"
                    :class="index === activeIndex ? 'bg-brand-50 text-brand-900' : 'text-slate-700'"
                    @mousedown.prevent="choose(option)"
                    @mouseenter="activeIndex = index"
                >
                    <span class="block font-medium">{{ option.label }}</span>
                    <span v-if="option.description" class="block text-xs text-slate-500">{{ option.description }}</span>
                </li>
                <li v-if="!loading && !results.length" class="px-3 py-2 text-sm text-slate-500">Nenhum resultado.</li>
            </ul>
        </div>
    </FormField>
</template>
