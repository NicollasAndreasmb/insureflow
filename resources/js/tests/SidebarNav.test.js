import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import SidebarNav from '@/components/layout/SidebarNav.vue';
import { navigationSections } from '@/components/layout/navigation';
import { useAuthStore } from '@/stores/auth';

const routeNames = navigationSections.flatMap((section) => section.items.map((item) => item.to.name));

function mountNav(permissions) {
    const pinia = createPinia();
    setActivePinia(pinia);
    useAuthStore().user = { name: 'Teste', permissions };

    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', component: { template: '<div />' } },
            ...routeNames.map((name) => ({ path: `/${name}`, name, component: { template: '<div />' } })),
        ],
    });

    return mount(SidebarNav, { global: { plugins: [pinia, router] } });
}

beforeEach(() => setActivePinia(createPinia()));

describe('SidebarNav', () => {
    it('shows administration items only when the backend grants them', () => {
        const assistant = mountNav({ 'users.manage': false, 'activity_logs.view': true });

        expect(assistant.text()).toContain('Apólices');
        expect(assistant.text()).toContain('Auditoria');
        expect(assistant.text()).not.toContain('Usuários');

        const admin = mountNav({ 'users.manage': true, 'activity_logs.view': true });

        expect(admin.text()).toContain('Usuários');
    });

    it('hides a whole section when none of its items are allowed', () => {
        const wrapper = mountNav({});

        expect(wrapper.text()).not.toContain('Administração');
    });
});
