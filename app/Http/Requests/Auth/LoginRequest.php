<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Valida e executa a tentativa de login da SPA (guard de sessão "web").
 *
 * Credenciais inválidas e e-mails inexistentes recebem a mesma mensagem, para
 * não revelar quais contas existem. A mensagem de conta inativa só aparece
 * quando a senha está correta. O limite de tentativas é aplicado pela rota
 * (RateLimiter "login", em AppServiceProvider).
 */
class LoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function authenticate(): User
    {
        $isInactive = false;

        $authenticated = Auth::guard('web')->attemptWhen(
            $this->only('email', 'password'),
            function (User $user) use (&$isInactive) {
                $isInactive = ! $user->is_active;

                return $user->is_active;
            },
            $this->boolean('remember'),
        );

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => $isInactive ? __('auth.inactive') : __('auth.failed'),
            ]);
        }

        return Auth::guard('web')->user();
    }
}
