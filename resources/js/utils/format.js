/*
 * Formatação para exibição. A API envia dinheiro como string decimal
 * ("1234.56"), datas como "YYYY-MM-DD" e timestamps em ISO 8601 com offset;
 * aqui eles viram texto pt-BR, sem alterar o valor original.
 *
 * Datas simples nunca passam por `new Date("YYYY-MM-DD")`: o JavaScript as
 * interpreta como meia-noite UTC, o que no fuso de São Paulo exibiria o dia
 * anterior. Por isso são formatadas a partir da própria string.
 */
const APP_TIMEZONE = 'America/Sao_Paulo';

const currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const numberFormatter = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 });
const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: APP_TIMEZONE,
});
const isoDateFormatter = new Intl.DateTimeFormat('en-CA', { timeZone: APP_TIMEZONE });

export function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return currencyFormatter.format(Number(value));
}

export function formatNumber(value) {
    return numberFormatter.format(Number(value ?? 0));
}

export function formatPercent(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return `${numberFormatter.format(Number(value))}%`;
}

export function formatDate(value) {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.slice(0, 10).split('-');

    return `${day}/${month}/${year}`;
}

export function formatDateTime(value) {
    return value ? dateTimeFormatter.format(new Date(value)) : '—';
}

export function todayIso() {
    return isoDateFormatter.format(new Date());
}

export function formatDocument(value) {
    const digits = String(value ?? '').replace(/\D/g, '');

    if (digits.length === 11) {
        return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }

    if (digits.length === 14) {
        return digits.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    }

    return value || '—';
}

export function formatPhone(value) {
    const digits = String(value ?? '').replace(/\D/g, '');

    if (digits.length === 11) {
        return digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    }

    if (digits.length === 10) {
        return digits.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    }

    return value || '—';
}

export function formatZipCode(value) {
    const digits = String(value ?? '').replace(/\D/g, '');

    return digits.length === 8 ? digits.replace(/(\d{5})(\d{3})/, '$1-$2') : value || '—';
}
