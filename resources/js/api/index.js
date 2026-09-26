/*
 * Pontos de acesso à API por módulo. As rotas espelham routes/api.php; ações que
 * não são CRUD (transições de status, pagamento) têm funções próprias.
 */
import http from './http';
import { cleanParams, createResource } from './resource';

export const clientsApi = createResource('/clients');
export const insurersApi = createResource('/insurers');
export const productsApi = createResource('/insurance-products');
export const usersApi = createResource('/users');

export const policiesApi = {
    ...createResource('/policies'),
    async changeStatus(id, status) {
        const { data } = await http.patch(`/policies/${id}/status`, { status });

        return data.data;
    },
    async listCoverages(id) {
        const { data } = await http.get(`/policies/${id}/coverages`);

        return data.data;
    },
    async createCoverage(id, payload) {
        const { data } = await http.post(`/policies/${id}/coverages`, payload);

        return data.data;
    },
};

export const coveragesApi = {
    async update(id, payload) {
        const { data } = await http.put(`/coverages/${id}`, payload);

        return data.data;
    },
    async remove(id) {
        await http.delete(`/coverages/${id}`);
    },
};

export const installmentsApi = {
    async list(params = {}, options = {}) {
        const { data } = await http.get('/installments', { params: cleanParams(params), ...options });

        return data;
    },
    async pay(id, paidOn) {
        const { data } = await http.post(`/installments/${id}/payment`, { paid_on: paidOn });

        return data.data;
    },
};

export const claimsApi = {
    ...createResource('/claims'),
    async changeStatus(id, payload) {
        const { data } = await http.patch(`/claims/${id}/status`, payload);

        return data.data;
    },
};

export const activityLogsApi = {
    async list(params = {}, options = {}) {
        const { data } = await http.get('/activity-logs', { params: cleanParams(params), ...options });

        return data;
    },
};

export async function fetchDashboard(params = {}) {
    const { data } = await http.get('/dashboard', { params: cleanParams(params) });

    return data.data;
}

export async function fetchOptions() {
    const { data } = await http.get('/options');

    return data.data;
}
