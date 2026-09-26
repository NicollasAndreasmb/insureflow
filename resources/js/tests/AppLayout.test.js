import { flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { navigationSections } from '@/components/layout/navigation';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { mountPage } from './support';

vi.mock('@/api', () => ({ fetchOptions: vi.fn(() => Promise.resolve({})) }));

const titled = (title) => defineComponent({ render: () => h(PageHeader, { title }) });

const pages = { 'clients.index': 'Clientes', 'policies.index': 'Apólices' };

const routes = navigationSections
    .flatMap((section) => section.items.map((item) => item.to.name))
    .map((name) => ({
        path: name === 'dashboard' ? '/app' : `/app/${name.split('.')[0]}`,
        name,
        component: titled(pages[name] ?? name),
    }));

describe('AppLayout focus management', () => {
    it('moves focus into the mobile drawer and back to the menu button', async () => {
        const { wrapper } = await mountPage(AppLayout, { route: '/app/clients', routes });
        const menuButton = wrapper.find('button[aria-label="Abrir menu"]');

        await menuButton.trigger('click');
        await flushPromises();

        expect(document.activeElement.getAttribute('aria-label')).toBe('Fechar menu');
        expect(wrapper.find('.lg\\:pl-64').attributes('inert')).toBeDefined();

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flushPromises();

        expect(document.activeElement).toBe(menuButton.element);
        expect(wrapper.find('.lg\\:pl-64').attributes('inert')).toBeUndefined();
        wrapper.unmount();
    });

    it('focuses the new page title after navigating', async () => {
        const { wrapper, router } = await mountPage(AppLayout, { route: '/app/clients', routes });

        await router.push('/app/policies');
        await flushPromises();

        expect(document.activeElement.tagName).toBe('H1');
        expect(document.activeElement.textContent).toBe('Apólices');
        wrapper.unmount();
    });
});
