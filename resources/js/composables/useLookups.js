/*
 * Listas curtas para selects de formulários e filtros (seguradoras,
 * modalidades, corretores), buscadas na API com o filtro `is_active` quando
 * se trata de novos vínculos. Clientes e apólices, que podem ser muitos,
 * usam RemoteSelect com busca em vez destas listas.
 */
import { insurersApi, productsApi, usersApi } from '@/api';

const MAX_OPTIONS = 100;

async function fetchOptions(api, params, toOption) {
    const response = await api.list({ per_page: MAX_OPTIONS, ...params });

    return response.data.map(toOption);
}

export function fetchInsurerOptions({ activeOnly = false } = {}) {
    return fetchOptions(insurersApi, { is_active: activeOnly ? 1 : '', sort: 'trade_name' }, (insurer) => ({
        value: insurer.id,
        label: insurer.trade_name,
    }));
}

export function fetchProductOptions({ activeOnly = false } = {}) {
    return fetchOptions(productsApi, { is_active: activeOnly ? 1 : '', sort: 'name' }, (product) => ({
        value: product.id,
        label: product.name,
    }));
}

export async function fetchBrokerOptions() {
    const [brokers, admins] = await Promise.all(
        ['broker', 'admin'].map((role) =>
            fetchOptions(usersApi, { role, is_active: 1, sort: 'name' }, (user) => ({ value: user.id, label: `${user.name} (${user.role.label})` })),
        ),
    );

    return [...brokers, ...admins];
}
