import { AxiosError } from 'axios';
import { afterEach, describe, expect, it, vi } from 'vitest';
import http, { ApiError, onUnauthenticated } from '@/api/http';

function respondWith(status, data = {}) {
    return (config) => {
        if (status >= 400) {
            return Promise.reject(new AxiosError('failed', 'ERR', config, null, { status, data, headers: {}, config }));
        }

        return Promise.resolve({ status, data, headers: {}, config });
    };
}

const originalAdapter = http.defaults.adapter;

afterEach(() => {
    http.defaults.adapter = originalAdapter;
    onUnauthenticated(null);
});

describe('http client', () => {
    it('converts validation errors into ApiError with field errors', async () => {
        http.defaults.adapter = respondWith(422, { message: 'Os dados informados são inválidos.', errors: { name: ['Obrigatório'] } });

        const error = await http.post('/clients', {}).catch((e) => e);

        expect(error).toBeInstanceOf(ApiError);
        expect(error.isValidation).toBe(true);
        expect(error.errors).toEqual({ name: ['Obrigatório'] });
        expect(error.message).toBe('Os dados informados são inválidos.');
    });

    it('notifies the session handler on 401 unless the request opts out', async () => {
        const handler = vi.fn();
        onUnauthenticated(handler);
        http.defaults.adapter = respondWith(401, { message: 'Não autenticado.' });

        await http.get('/clients').catch(() => {});
        await http.get('/user', { skipAuthRedirect: true }).catch(() => {});

        expect(handler).toHaveBeenCalledTimes(1);
    });

    it('refreshes the CSRF cookie and retries once on 419', async () => {
        const calls = [];
        http.defaults.adapter = (config) => {
            calls.push(config.url);

            return calls.length === 1 ? respondWith(419)(config) : respondWith(200, { ok: true })(config);
        };

        const response = await http.post('/logout');

        expect(calls).toEqual(['/logout', '/sanctum/csrf-cookie', '/logout']);
        expect(response.data).toEqual({ ok: true });
    });

    it('reports network failures with a friendly message', async () => {
        http.defaults.adapter = (config) => Promise.reject(new AxiosError('Network Error', 'ERR_NETWORK', config));

        const error = await http.get('/clients').catch((e) => e);

        expect(error.status).toBe(0);
        expect(error.message).toContain('Não foi possível conectar');
    });
});
