/*
 * Estado de um formulário ligado à API.
 *
 * submit(action) executa a chamada, bloqueia envios duplicados enquanto
 * `processing` estiver ativo e distribui a resposta de erro: um 422 preenche
 * `errors` por campo (inclusive chaves aninhadas como "coverages.0.name") e
 * `generalError` com a mensagem geral — que também cobre regras de negócio
 * sem campo associado. Outros erros (403, 500, rede) viram `generalError`.
 * Nenhum erro é descartado silenciosamente.
 *
 * `rules` (objeto ou função dos campos, para regras condicionais) valida no
 * navegador antes do envio, com as regras de utils/validation.js: se houver
 * erro, a requisição nem é feita e os erros aparecem no mesmo formato do 422.
 */
import { reactive, ref } from 'vue';
import { ApiError } from '@/api/http';
import { validate } from '@/utils/validation';

export const CLIENT_VALIDATION_MESSAGE = 'Corrija os campos destacados antes de continuar.';

export function useForm(initialValues = {}, { rules = null } = {}) {
    const fields = reactive({ ...initialValues });
    const errors = ref({});
    const generalError = ref('');
    const processing = ref(false);

    function error(field) {
        return errors.value[field]?.[0] ?? '';
    }

    function clearErrors() {
        errors.value = {};
        generalError.value = '';
    }

    function reset(values = initialValues) {
        Object.keys(fields).forEach((key) => delete fields[key]);
        Object.assign(fields, values);
        clearErrors();
    }

    async function submit(action) {
        if (processing.value) {
            return null;
        }

        clearErrors();

        if (rules) {
            const clientErrors = validate(fields, typeof rules === 'function' ? rules(fields) : rules);

            if (Object.keys(clientErrors).length > 0) {
                errors.value = clientErrors;
                generalError.value = CLIENT_VALIDATION_MESSAGE;

                return null;
            }
        }

        processing.value = true;

        try {
            return await action({ ...fields });
        } catch (exception) {
            if (!(exception instanceof ApiError)) {
                throw exception;
            }

            errors.value = exception.isValidation ? exception.errors : {};
            generalError.value = exception.message;

            return null;
        } finally {
            processing.value = false;
        }
    }

    return reactive({ fields, errors, generalError, processing, error, submit, reset, clearErrors });
}
