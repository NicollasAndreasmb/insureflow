import { describe, expect, it } from 'vitest';
import { useForm } from '@/composables/useForm';
import {
    cnpj,
    cpf,
    dateAfter,
    digitsBetween,
    email,
    integerBetween,
    maxLength,
    money,
    password,
    required,
    url,
    validate,
} from '@/utils/validation';

describe('validation rules', () => {
    it('only required() rejects blank values', () => {
        expect(required()('  ')).not.toBe('');
        expect(email()('')).toBe('');
        expect(maxLength(3)(null)).toBe('');
    });

    it('mirrors the backend rules', () => {
        expect(maxLength(3)('abcd')).not.toBe('');
        expect(email()('ana@insureflow.test')).toBe('');
        expect(email()('ana@')).not.toBe('');
        expect(url()('javascript:alert(1)')).not.toBe('');
        expect(url()('https://insureflow.test')).toBe('');
        expect(digitsBetween(10, 11)('(11) 98888-7777')).toBe('');
        expect(digitsBetween(8)('0100-000')).not.toBe('');
        expect(money()('1500.50')).toBe('');
        expect(money()('1500.505')).not.toBe('');
        expect(money()('-1')).not.toBe('');
        expect(integerBetween(1, 12)('13')).not.toBe('');
    });

    it('validates CPF and CNPJ check digits', () => {
        expect(cpf()('529.982.247-25')).toBe('');
        expect(cpf()('529.982.247-24')).not.toBe('');
        expect(cpf()('111.111.111-11')).not.toBe('');
        expect(cnpj()('11.222.333/0001-81')).toBe('');
        expect(cnpj()('11.222.333/0001-80')).not.toBe('');
    });

    it('follows the password policy', () => {
        expect(password()('SenhaSegura123')).toBe('');
        expect(password()('Curta123')).not.toBe('');
        expect(password()('semmaiuscula123')).not.toBe('');
        expect(password()('SemNumeroAlgum')).not.toBe('');
    });

    it('compares dates with other fields', () => {
        const rule = dateAfter('starts_on', 'depois');

        expect(rule('2026-01-01', { starts_on: '2026-01-01' })).toBe('depois');
        expect(rule('2026-01-02', { starts_on: '2026-01-01' })).toBe('');
    });

    it('returns errors in the API 422 format, one message per field', () => {
        expect(validate({ name: '', email: 'x' }, { name: [required(), maxLength(5)], email: [email()] })).toEqual({
            name: ['Este campo é obrigatório.'],
            email: ['Informe um e-mail válido.'],
        });
    });
});

describe('useForm with rules', () => {
    it('does not run the action when the browser validation fails', async () => {
        const form = useForm({ name: '' }, { rules: { name: [required()] } });
        let called = false;

        const result = await form.submit(async () => {
            called = true;
        });

        expect(result).toBeNull();
        expect(called).toBe(false);
        expect(form.error('name')).toBe('Este campo é obrigatório.');
        expect(form.processing).toBe(false);
    });

    it('evaluates rule functions with the current fields', async () => {
        const form = useForm({ type: 'company', document: '529.982.247-25' }, {
            rules: (fields) => ({ document: [fields.type === 'company' ? cnpj() : cpf()] }),
        });

        await form.submit(async () => true);
        expect(form.error('document')).toBe('Informe um CNPJ válido.');

        form.fields.type = 'individual';
        expect(await form.submit(async () => true)).toBe(true);
    });
});
