/*
 * Conversão entre o formato digitado pelo usuário ("1.234,56") e o formato da
 * API (string decimal "1234.56"). Valores monetários nunca são convertidos para
 * number no envio, evitando erros de ponto flutuante.
 */
export function parseMoneyInput(text) {
    const cleaned = String(text ?? '')
        .replace(/[^\d,.-]/g, '')
        .trim();

    if (cleaned === '') {
        return '';
    }

    const normalized = cleaned.includes(',') ? cleaned.replace(/\./g, '').replace(',', '.') : cleaned;
    const [integerPart, decimalPart = ''] = normalized.split('.');
    const integer = integerPart.replace(/^0+(?=\d)/, '') || '0';

    return `${integer}.${decimalPart.padEnd(2, '0').slice(0, 2)}`;
}

export function formatMoneyInput(value) {
    if (value === '' || value === null || value === undefined) {
        return '';
    }

    const [integerPart, decimalPart = '00'] = String(value).split('.');
    const grouped = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${grouped},${decimalPart.padEnd(2, '0').slice(0, 2)}`;
}
