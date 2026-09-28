<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Criação e edição de usuários (somente administradores).
 *
 * Na edição a senha é opcional (mantida quando não enviada). Um administrador
 * não pode alterar o próprio perfil nem desativar a própria conta, evitando
 * que o sistema fique sem acesso administrativo por engano.
 */
class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->editedUser();

        return $user
            ? $this->user()->can('update', $user)
            : $this->user()->can('create', User::class);
    }

    /**
     * E-mails são gravados em minúsculas: a comparação do PostgreSQL diferencia
     * maiúsculas, e sem isso "Ana@x" e "ana@x" seriam contas distintas.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $user = $this->editedUser();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->withoutTrashed()->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $user = $this->editedUser();

                if ($user === null || ! $user->is($this->user())) {
                    return;
                }

                if ($this->input('role') !== $user->role->value) {
                    $validator->errors()->add('role', 'Você não pode alterar o seu próprio perfil.');
                }

                if ($this->has('is_active') && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'Você não pode desativar a sua própria conta.');
                }
            },
        ];
    }

    public function userData(): array
    {
        $data = $this->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }

    private function editedUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
