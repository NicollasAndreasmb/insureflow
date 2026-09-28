/*
 * Validação de formulários no navegador.
 *
 * Espelha as regras dos FormRequests do backend para dar retorno imediato e
 * evitar requisições que certamente seriam recusadas. Não substitui a
 * validação do servidor, que continua sendo a autoridade: regras que dependem
 * do banco (unicidade, registros ativos, máquina de estados) só existem lá.
 *
 * Cada regra recebe (valor, campos) e devolve a mensagem de erro ou ''.
 * Exceto required(), as regras ignoram valores vazios, como `nullable` no Laravel.
 */
import { todayIso } from './format';
import { digitsOnly } from './masks';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function isBlank(value) {
    return value === null || value === undefined || (typeof value === 'string' && value.trim() === '');
}

function optional(check) {
    return (value, fields) => (isBlank(value) ? '' : check(value, fields));
}

export function required(message = 'Este campo é obrigatório.') {
    return (value) => (isBlank(value) ? message : '');
}

export function maxLength(max) {
    return optional((value) => (String(value).length > max ? `Use no máximo ${max} caracteres.` : ''));
}

export function email() {
    return optional((value) => (EMAIL_PATTERN.test(String(value).trim()) ? '' : 'Informe um e-mail válido.'));
}

export function url() {
    return optional((value) => {
        try {
            const parsed = new URL(String(value));

            return ['http:', 'https:'].includes(parsed.protocol) ? '' : 'Informe um endereço começando com http:// ou https://.';
        } catch {
            return 'Informe um endereço começando com http:// ou https://.';
        }
    });
}

export function digitsBetween(min, max = min) {
    const expected = min === max ? `${min} dígitos` : `de ${min} a ${max} dígitos`;

    return optional((value) => {
        const length = digitsOnly(value).length;

        return length < min || length > max ? `Informe ${expected}.` : '';
    });
}

export function password() {
    return optional((value) => {
        const text = String(value);

        if (text.length < 10 || text.length > 72) {
            return 'A senha deve ter entre 10 e 72 caracteres.';
        }

        return /[a-z]/.test(text) && /[A-Z]/.test(text) && /\d/.test(text)
            ? ''
            : 'A senha deve ter letras maiúsculas, minúsculas e números.';
    });
}

export function numberBetween(min, max) {
    return optional((value) => {
        const number = Number(value);

        if (!Number.isFinite(number)) {
            return 'Informe um número válido.';
        }

        return number < min || number > max ? `Informe um valor entre ${min} e ${max}.` : '';
    });
}

export function money() {
    const range = numberBetween(0, 9999999999.99);

    return optional((value, fields) => (/^\d+(\.\d{1,2})?$/.test(String(value).trim()) ? range(value, fields) : 'Informe um valor válido, com até 2 casas decimais.'));
}

export function integerBetween(min, max) {
    const range = numberBetween(min, max);

    return optional((value, fields) => (Number.isInteger(Number(value)) ? range(value, fields) : 'Informe um número inteiro.'));
}

export function dateNotAfterToday(message = 'A data não pode ser futura.') {
    return optional((value) => (String(value) > todayIso() ? message : ''));
}

export function dateBeforeToday(message = 'A data deve ser anterior a hoje.') {
    return optional((value) => (String(value) >= todayIso() ? message : ''));
}

export function dateAfter(field, message) {
    return optional((value, fields) => (!isBlank(fields[field]) && String(value) <= String(fields[field]) ? message : ''));
}

export function dateNotBefore(field, message) {
    return optional((value, fields) => (!isBlank(fields[field]) && String(value) < String(fields[field]) ? message : ''));
}

export function cpf() {
    return optional((value) => (isValidCpf(digitsOnly(value)) ? '' : 'Informe um CPF válido.'));
}

export function cnpj() {
    return optional((value) => (isValidCnpj(digitsOnly(value)) ? '' : 'Informe um CNPJ válido.'));
}

export function isValidCpf(value) {
    if (!/^\d{11}$/.test(value) || /^(\d)\1{10}$/.test(value)) {
        return false;
    }

    for (let position = 9; position < 11; position++) {
        let sum = 0;

        for (let index = 0; index < position; index++) {
            sum += Number(value[index]) * (position + 1 - index);
        }

        if (Number(value[position]) !== ((10 * sum) % 11) % 10) {
            return false;
        }
    }

    return true;
}

export function isValidCnpj(value) {
    if (!/^\d{14}$/.test(value) || /^(\d)\1{13}$/.test(value)) {
        return false;
    }

    const checkDigit = (weights) => {
        const remainder = weights.reduce((sum, weight, index) => sum + Number(value[index]) * weight, 0) % 11;

        return remainder < 2 ? 0 : 11 - remainder;
    };

    return (
        Number(value[12]) === checkDigit([5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]) &&
        Number(value[13]) === checkDigit([6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2])
    );
}

/** Regras do endereço compartilhado por clientes e seguradoras (ValidatesAddress). */
export function addressRules() {
    return {
        zip_code: [digitsBetween(8)],
        street: [maxLength(255)],
        number: [maxLength(20)],
        complement: [maxLength(100)],
        district: [maxLength(100)],
        city: [maxLength(100)],
    };
}

/**
 * Aplica as regras e devolve os erros no mesmo formato de um 422 da API
 * ({ campo: [mensagem] }), para que as telas exibam ambos da mesma forma.
 */
export function validate(fields, rules) {
    const errors = {};

    for (const [field, fieldRules] of Object.entries(rules)) {
        for (const rule of fieldRules) {
            const message = rule(fields[field], fields);

            if (message) {
                errors[field] = [message];
                break;
            }
        }
    }

    return errors;
}
