import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { createMemoryHistory, createRouter } from 'vue-router';
import { usePaginatedList } from '@/composables/usePaginatedList';

async function setup(url, fetcher) {
    let list;
    const Page = defineComponent({
        setup() {
            list = usePaginatedList(fetcher, { filterKeys: ['search'], defaultSort: 'name' });

            return () => h('div');
        },
    });
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/items', name: 'items', component: Page }] });
    await router.push(url);
    await router.isReady();
    mount({ render: () => h(Page) }, { global: { plugins: [router] } });
    await flushPromises();

    return { list, router };
}

const page = (data, current, last) => ({ data, meta: { current_page: current, last_page: last, from: 1, to: data.length, total: data.length } });

describe('usePaginatedList', () => {
    it('goes back to the last existing page when the requested one no longer exists', async () => {
        const fetcher = vi.fn((params) => Promise.resolve(params.page === 3 ? page([], 3, 2) : page([{ id: 1 }], 2, 2)));

        const { router } = await setup('/items?page=3&search=ana', fetcher);
        await flushPromises();

        expect(router.currentRoute.value.query).toEqual({ page: '2', search: 'ana' });
        expect(fetcher).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2, search: 'ana' }), expect.anything());
    });

    it('resets to the first page when filters change', async () => {
        const fetcher = vi.fn(() => Promise.resolve(page([{ id: 1 }], 1, 1)));
        const { list, router } = await setup('/items?page=4', fetcher);

        await list.applyFilters({ search: 'maria' });
        await flushPromises();

        expect(router.currentRoute.value.query).toEqual({ search: 'maria' });
    });
});
