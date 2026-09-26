import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DataTable from '@/components/data/DataTable.vue';
import MaskedInput from '@/components/form/MaskedInput.vue';
import MoneyInput from '@/components/form/MoneyInput.vue';
import TextInput from '@/components/form/TextInput.vue';
import AppModal from '@/components/ui/AppModal.vue';

describe('TextInput', () => {
    it('links label, error message and aria-invalid for screen readers', () => {
        const wrapper = mount(TextInput, { props: { label: 'E-mail', error: 'O campo e-mail é obrigatório.' } });
        const input = wrapper.find('input');
        const label = wrapper.find('label');
        const errorId = input.attributes('aria-describedby');

        expect(label.attributes('for')).toBe(input.attributes('id'));
        expect(input.attributes('aria-invalid')).toBe('true');
        expect(wrapper.find(`#${errorId}`).text()).toBe('O campo e-mail é obrigatório.');
    });
});

describe('MoneyInput', () => {
    it('exposes the API decimal format while showing Brazilian formatting', async () => {
        const wrapper = mount(MoneyInput, { props: { modelValue: '1500.00', 'onUpdate:modelValue': (value) => wrapper.setProps({ modelValue: value }) } });
        const input = wrapper.find('input');

        expect(input.element.value).toBe('1.500,00');

        await input.setValue('2.345,6');
        expect(wrapper.props('modelValue')).toBe('2345.60');

        await input.trigger('blur');
        expect(input.element.value).toBe('2.345,60');
    });
});

describe('MaskedInput', () => {
    it('masks the displayed value and emits digits only', async () => {
        const wrapper = mount(MaskedInput, { props: { mask: 'cpf', modelValue: '', 'onUpdate:modelValue': (value) => wrapper.setProps({ modelValue: value }) } });

        await wrapper.find('input').setValue('52998224725');

        expect(wrapper.props('modelValue')).toBe('52998224725');
        expect(wrapper.find('input').element.value).toBe('529.982.247-25');
    });
});

describe('DataTable', () => {
    const columns = [
        { key: 'name', label: 'Nome', sortable: true },
        { key: 'city', label: 'Cidade' },
    ];

    it('announces the current sort and emits the toggled direction', async () => {
        const wrapper = mount(DataTable, { props: { columns, rows: [{ id: 1, name: 'Ana', city: 'Campinas' }], sort: 'name' } });

        expect(wrapper.find('th').attributes('aria-sort')).toBe('ascending');
        expect(wrapper.findAll('th')[1].attributes('aria-sort')).toBeUndefined();

        await wrapper.find('th button').trigger('click');
        expect(wrapper.emitted('sort')[0]).toEqual(['-name']);
    });

    it('shows loading and empty states', async () => {
        const wrapper = mount(DataTable, { props: { columns, rows: [], loading: true } });
        expect(wrapper.find('[role="status"]').exists()).toBe(true);

        await wrapper.setProps({ loading: false });
        expect(wrapper.text()).toContain('Nenhum registro encontrado');
    });
});

describe('AppModal', () => {
    it('is an accessible dialog that moves focus inside and closes on Escape', async () => {
        const wrapper = mount(AppModal, {
            props: { open: false, title: 'Editar cobertura' },
            slots: { default: '<input id="first-field" />' },
            attachTo: document.body,
        });

        await wrapper.setProps({ open: true });
        await flushPromises();
        const dialog = document.querySelector('[role="dialog"]');

        expect(dialog.getAttribute('aria-modal')).toBe('true');
        expect(document.getElementById(dialog.getAttribute('aria-labelledby')).textContent).toBe('Editar cobertura');
        expect(dialog.contains(document.activeElement)).toBe(true);

        dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        expect(wrapper.emitted('close')).toHaveLength(1);

        wrapper.unmount();
    });
});
