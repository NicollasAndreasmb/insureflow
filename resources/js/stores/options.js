/*
 * Valores e rótulos fornecidos por /api/options (enums, UFs, nomes de entidades
 * e de campos). Carregados uma vez por sessão e usados por selects, filtros e
 * badges, para que nenhum rótulo de enum seja duplicado no frontend.
 */
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { fetchOptions } from '@/api';

export const useOptionsStore = defineStore('options', () => {
    const options = ref({});
    const loaded = ref(false);
    let loading = null;

    function load() {
        loading ??= fetchOptions()
            .then((data) => {
                options.value = data;
                loaded.value = true;
            })
            .catch((error) => {
                loading = null;
                throw error;
            });

        return loading;
    }

    function list(key) {
        return options.value[key] ?? [];
    }

    function label(key, value) {
        return list(key).find((option) => option.value === value)?.label ?? value;
    }

    function fieldLabel(field) {
        return options.value.field_labels?.[field] ?? field.replaceAll('_', ' ');
    }

    return { options, loaded, load, list, label, fieldLabel };
});
