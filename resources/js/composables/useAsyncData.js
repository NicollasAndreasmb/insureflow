import { ref, watch } from 'vue';
import { toApiError } from '@/api/http';

/**
 * Carrega dados de uma tela de detalhe e expõe {data, loading, error, reload}.
 * `source` é uma função reativa (ex.: () => route.params.id); quando ela muda,
 * os dados são recarregados.
 */
export function useAsyncData(fetcher, source = () => null) {
    const data = ref(null);
    const loading = ref(true);
    const error = ref(null);

    async function reload() {
        loading.value = true;
        error.value = null;

        try {
            data.value = await fetcher(source());
        } catch (exception) {
            error.value = toApiError(exception);
        } finally {
            loading.value = false;
        }
    }

    watch(source, reload, { immediate: true });

    return { data, loading, error, reload };
}
