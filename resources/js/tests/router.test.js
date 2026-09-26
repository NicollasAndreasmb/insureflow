import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { fetchCurrentUser } from '@/api/auth';
import { installGuards, safeRedirect } from '@/router/guards';

vi.mock('@/api/auth', () => ({ fetchCurrentUser: vi.fn(), login: vi.fn(), logout: vi.fn() }));

const Page = { template: '<div />' };

function makeRouter() {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/login', name: 'login', component: Page, meta: { guest: true } },
            {
                path: '/',
                component: Page,
                meta: { requiresAuth: true },
                children: [
                    { path: '', name: 'dashboard', component: Page },
                    { path: 'clients', name: 'clients.index', component: Page },
                    { path: 'users', name: 'users.index', component: Page, meta: { permission: 'users.manage' } },
                    { path: 'forbidden', name: 'forbidden', component: Page },
                ],
            },
        ],
    });
    installGuards(router);

    return router;
}

const broker = { id: 1, name: 'Bruno', permissions: { 'users.manage': false } };

beforeEach(() => {
    setActivePinia(createPinia());
    fetchCurrentUser.mockReset();
});

describe('route guards', () => {
    it('sends guests to the login page keeping the requested path', async () => {
        fetchCurrentUser.mockRejectedValue(new Error('401'));
        const router = makeRouter();

        await router.push('/clients?search=ana');

        expect(router.currentRoute.value.name).toBe('login');
        expect(router.currentRoute.value.query.redirect).toBe('/clients?search=ana');
    });

    it('lets authenticated users in and loads the session only once', async () => {
        fetchCurrentUser.mockResolvedValue(broker);
        const router = makeRouter();

        await router.push('/clients');
        await router.push('/');

        expect(router.currentRoute.value.name).toBe('dashboard');
        expect(fetchCurrentUser).toHaveBeenCalledTimes(1);
    });

    it('redirects authenticated users away from the login page', async () => {
        fetchCurrentUser.mockResolvedValue(broker);
        const router = makeRouter();

        await router.push('/login');

        expect(router.currentRoute.value.name).toBe('dashboard');
    });

    it('blocks routes whose permission the backend did not grant', async () => {
        fetchCurrentUser.mockResolvedValue(broker);
        const router = makeRouter();

        await router.push('/users');

        expect(router.currentRoute.value.name).toBe('forbidden');
    });

    it('only accepts internal redirect targets', () => {
        expect(safeRedirect('/policies/3')).toBe('/policies/3');
        expect(safeRedirect('//evil.example')).toBeNull();
        expect(safeRedirect('https://evil.example')).toBeNull();
    });
});
