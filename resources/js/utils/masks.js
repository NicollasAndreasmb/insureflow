/*
 * Máscaras de digitação. Aplicam apenas formatação visual enquanto o usuário
 * digita; o valor enviado à API é sempre o de digitsOnly(), no formato que o
 * backend grava (somente dígitos).
 */
export function digitsOnly(value) {
    return String(value ?? '').replace(/\D/g, '');
}

function applyPattern(digits, pattern) {
    let result = '';
    let index = 0;

    for (const char of pattern) {
        if (index >= digits.length) {
            break;
        }

        result += char === '#' ? digits[index++] : char;
    }

    return result;
}

export const MASKS = {
    cpf: { pattern: '###.###.###-##', maxDigits: 11 },
    cnpj: { pattern: '##.###.###/####-##', maxDigits: 14 },
    phone: { pattern: '(##) #####-####', maxDigits: 11 },
    landline: { pattern: '(##) ####-####', maxDigits: 10 },
    zip: { pattern: '#####-###', maxDigits: 8 },
};

export function applyMask(value, maskName) {
    const digits = digitsOnly(value);

    if (maskName === 'phone' && digits.length <= 10) {
        return applyPattern(digits, MASKS.landline.pattern);
    }

    const mask = MASKS[maskName];

    return applyPattern(digits.slice(0, mask.maxDigits), mask.pattern);
}
