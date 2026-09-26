/*
 * Listagem paginada, filtrável e ordenável consumindo os mecanismos da API.
 *
 * A query string da URL é a fonte da verdade (?search=...&status=...&sort=-name
 * &page=2): assim filtros sobrevivem a refresh, podem ser compartilhados por
 * link e o botão "voltar" funciona. Alterar filtros volta para a página 1.
 * Nada é filtrado ou ordenado no navegador. Uma requisição em andamento é
 * cancelada quando outra começa, evitando que uma resposta antiga sobrescreva
 * a mais recente.
 */
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toApiError } from '@/api/http';

export function usePaginatedList(fetcher, { filterKeys = [], defaultSort = '', perPage = 15 } = {}) {
    const route = useRoute();
    const router = useRouter();
    const routeName = route.name;

    const items = ref([]);
    const meta = ref(null);
    const loading = ref(false);
    const error = ref(null);
    const filters = reactive(readFilters());
    let controller = null;

    const sort = computed(() => route.query.sort ?? defaultSort);
    const page = computed(() => Number(route.query.page ?? 1));
    const hasActiveFilters = computed(() => filterKeys.some((key) => route.query[key]));

    function readFilters() {
        return Object.fromEntries(filterKeys.map((key) => [key, route.query[key] ?? '']));
    }

    function updateQuery(changes) {
        const query = { ...route.query, ...changes };
        Object.keys(query).forEach((key) => {
            if (query[key] === '' || query[key] === null || query[key] === undefined) {
                delete query[key];
            }
        });

        return router.replace({ query });
    }

    async function load() {
        controller?.abort();
        const current = new AbortController();
        controller = current;
        loading.value = true;
        error.value = null;

        try {
            const response = await fetcher(
                { ...readFilters(), sort: sort.value, page: page.value, per_page: perPage },
                { signal: current.signal },
            );
            items.value = response.data;
            meta.value = response.meta;
        } catch (exception) {
            if (exception?.name !== 'CanceledError') {
                error.value = toApiError(exception);
            }
        } finally {
            if (controller === current) {
                loading.value = false;
            }
        }
    }

    function applyFilters(changes = filters) {
        return updateQuery({ ...changes, page: undefined });
    }

    function clearFilters() {
        filterKeys.forEach((key) => {
            filters[key] = '';
        });

        return applyFilters();
    }

    watch(
        () => route.query,
        () => {
            if (route.name !== routeName) {
                return;
            }
            Object.assign(filters, readFilters());
            load();
        },
        { immediate: true },
    );

    onBeforeUnmount(() => controller?.abort());

    return reactive({
        items,
        meta,
        loading,
        error,
        filters,
        sort,
        hasActiveFilters,
        reload: load,
        applyFilters,
        clearFilters,
        setSort: (value) => updateQuery({ sort: value, page: undefined }),
        setPage: (value) => updateQuery({ page: value > 1 ? value : undefined }),
    });
}
