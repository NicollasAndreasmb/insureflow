/*
 * Fábrica de clientes para recursos REST (index/show/store/update/destroy).
 *
 * list() devolve o payload paginado completo ({data, links, meta}); as demais
 * operações devolvem apenas o objeto em "data". Parâmetros vazios são removidos
 * para que a query string reflita somente os filtros realmente aplicados.
 */
import http from './http';

export function cleanParams(params = {}) {
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
}

export function createResource(path) {
    return {
        async list(params = {}, options = {}) {
            const { data } = await http.get(path, { params: cleanParams(params), ...options });

            return data;
        },
        async get(id) {
            const { data } = await http.get(`${path}/${id}`);

            return data.data;
        },
        async create(payload) {
            const { data } = await http.post(path, payload);

            return data.data;
        },
        async update(id, payload, method = 'put') {
            const { data } = await http[method](`${path}/${id}`, payload);

            return data.data;
        },
        async remove(id) {
            await http.delete(`${path}/${id}`);
        },
    };
}
