import { describe, expect, it } from 'vitest';
import { formatDate, formatDocument, formatMoney, formatPhone } from '@/utils/format';
import { applyMask, digitsOnly } from '@/utils/masks';
import { formatMoneyInput, parseMoneyInput } from '@/utils/money';

describe('format', () => {
    it('formats API dates without shifting the day by timezone', () => {
        expect(formatDate('2026-09-26')).toBe('26/09/2026');
        expect(formatDate(null)).toBe('—');
    });

    it('formats money strings as BRL', () => {
        expect(formatMoney('1234.5').replace(/\s/g, ' ')).toBe('R$ 1.234,50');
        expect(formatMoney(null)).toBe('—');
    });

    it('formats documents and phones stored as digits', () => {
        expect(formatDocument('52998224725')).toBe('529.982.247-25');
        expect(formatDocument('11222333000181')).toBe('11.222.333/0001-81');
        expect(formatPhone('11987654321')).toBe('(11) 98765-4321');
        expect(formatPhone('1133334444')).toBe('(11) 3333-4444');
    });
});

describe('masks', () => {
    it('masks while typing but exposes digits only', () => {
        expect(applyMask('5299822', 'cpf')).toBe('529.982.2');
        expect(applyMask('112223330001819999', 'cnpj')).toBe('11.222.333/0001-81');
        expect(applyMask('1133334444', 'phone')).toBe('(11) 3333-4444');
        expect(digitsOnly('(11) 98765-4321')).toBe('11987654321');
    });
});

describe('money input', () => {
    it('converts Brazilian input into the API decimal string', () => {
        expect(parseMoneyInput('1.234,56')).toBe('1234.56');
        expect(parseMoneyInput('1234,5')).toBe('1234.50');
        expect(parseMoneyInput('1500')).toBe('1500.00');
        expect(parseMoneyInput('')).toBe('');
    });

    it('formats API decimals for editing', () => {
        expect(formatMoneyInput('1234567.8')).toBe('1.234.567,80');
        expect(formatMoneyInput('')).toBe('');
    });
});
