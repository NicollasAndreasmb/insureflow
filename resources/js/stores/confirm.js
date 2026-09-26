/*
 * Diálogo de confirmação global para operações destrutivas.
 *
 * ask() abre o ConfirmDialog (montado uma única vez em App.vue) e devolve uma
 * Promise<boolean>, permitindo escrever `if (!(await confirm.ask(...))) return;`
 * sem espalhar modais de confirmação pelas páginas.
 */
import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useConfirmStore = defineStore('confirm', () => {
    const dialog = ref(null);
    let resolver = null;

    function ask({ title, message, confirmLabel = 'Confirmar', danger = false }) {
        resolver?.(false);
        dialog.value = { title, message, confirmLabel, danger };

        return new Promise((resolve) => {
            resolver = resolve;
        });
    }

    function answer(confirmed) {
        resolver?.(confirmed);
        resolver = null;
        dialog.value = null;
    }

    return { dialog, ask, answer };
});
