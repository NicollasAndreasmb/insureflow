/*
 * Estado de um formulário ligado à API.
 *
 * submit(action) executa a chamada, bloqueia envios duplicados enquanto
 * `processing` estiver ativo e distribui a resposta de erro: um 422 preenche
 * `errors` por campo (inclusive chaves aninhadas como "coverages.0.name") e
 * `generalError` com a mensagem geral — que também cobre regras de negócio
 * sem campo associado. Outros erros (403, 500, rede) viram `generalError`.
 * Nenhum erro é descartado silenciosamente.
 */
import { reactive, ref } from 'vue';
import { ApiError } from '@/api/http';

export function useForm(initialValues = {}) {
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

        processing.value = true;
        clearErrors();

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
