import { describe, expect, it, vi } from 'vitest';
import { ApiError } from '@/api/http';
import { useForm } from '@/composables/useForm';

describe('useForm', () => {
    it('maps 422 errors to fields and keeps the general message', async () => {
        const form = useForm({ name: '' });

        const result = await form.submit(() => {
            throw new ApiError({ status: 422, message: 'Os dados informados são inválidos.', errors: { name: ['O campo nome é obrigatório.'] } });
        });

        expect(result).toBeNull();
        expect(form.error('name')).toBe('O campo nome é obrigatório.');
        expect(form.generalError).toBe('Os dados informados são inválidos.');
    });

    it('shows business rule and server errors as a general message', async () => {
        const form = useForm();

        await form.submit(() => {
            throw new ApiError({ status: 403, message: 'Você não tem permissão para realizar esta ação.' });
        });

        expect(form.generalError).toBe('Você não tem permissão para realizar esta ação.');
        expect(form.errors).toEqual({});
    });

    it('prevents double submission while processing', async () => {
        const form = useForm();
        let release;
        const action = vi.fn(() => new Promise((resolve) => (release = resolve)));

        const first = form.submit(action);
        const second = await form.submit(action);

        expect(form.processing).toBe(true);
        expect(second).toBeNull();
        release('ok');
        expect(await first).toBe('ok');
        expect(action).toHaveBeenCalledTimes(1);
        expect(form.processing).toBe(false);
    });

    it('passes a snapshot of the fields and clears previous errors', async () => {
        const form = useForm({ name: 'Ana' });
        form.errors = { name: ['antigo'] };

        const payload = await form.submit((fields) => fields);

        expect(payload).toEqual({ name: 'Ana' });
        expect(form.error('name')).toBe('');
    });
});
