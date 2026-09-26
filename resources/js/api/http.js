/*
 * Cliente HTTP único da SPA. Nenhum componente usa o Axios diretamente: todas as
 * chamadas passam pelos módulos de api/, que usam esta instância.
 *
 * Autenticação: Sanctum em modo SPA. O navegador envia o cookie de sessão
 * (HttpOnly) e o Axios copia o cookie XSRF-TOKEN para o header X-XSRF-TOKEN.
 * Nenhum token é guardado em localStorage.
 *
 * Tratamento de respostas:
 *   - 419 (token CSRF expirado): renova o cookie e repete a requisição uma vez;
 *   - 401: avisa o handler registrado por onUnauthenticated() (sessão expirada),
 *     exceto em requisições marcadas com skipAuthRedirect (ex.: checagem inicial);
 *   - todo erro é convertido em ApiError {status, message, errors}, no mesmo
 *     formato padronizado pela API, para que telas e formulários tratem erros
 *     de forma uniforme.
 */
import axios from 'axios';

export class ApiError extends Error {
    constructor({ status, message, errors = {} }) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }

    get isValidation() {
        return this.status === 422;
    }

    get isNotFound() {
        return this.status === 404;
    }

    get isForbidden() {
        return this.status === 403;
    }
}

const NETWORK_ERROR_MESSAGE = 'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.';
const UNKNOWN_ERROR_MESSAGE = 'Ocorreu um erro inesperado. Tente novamente.';

const http = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

let unauthenticatedHandler = null;

export function onUnauthenticated(handler) {
    unauthenticatedHandler = handler;
}

export function fetchCsrfCookie() {
    return http.get('/sanctum/csrf-cookie', { baseURL: '/' });
}

export function toApiError(error) {
    if (error instanceof ApiError) {
        return error;
    }

    if (!error.response) {
        return new ApiError({ status: 0, message: NETWORK_ERROR_MESSAGE });
    }

    const { status, data } = error.response;

    return new ApiError({
        status,
        message: data?.message ?? UNKNOWN_ERROR_MESSAGE,
        errors: data?.errors ?? {},
    });
}

http.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (axios.isCancel(error)) {
            throw error;
        }

        const { response, config } = error;

        if (response?.status === 419 && config && !config._csrfRetried) {
            config._csrfRetried = true;
            await fetchCsrfCookie();

            return http(config);
        }

        if (response?.status === 401 && !config?.skipAuthRedirect) {
            unauthenticatedHandler?.();
        }

        throw toApiError(error);
    },
);

export default http;
