<?php

namespace App\Http\Requests\Client;

use App\Enums\PersonType;
use App\Http\Requests\Concerns\NormalizesInput;
use App\Http\Requests\Concerns\ValidatesAddress;
use App\Models\Client;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação (POST) e substituição (PUT) de clientes: as regras são as mesmas,
 * e na edição a unicidade do documento ignora o próprio cliente.
 */
class SaveClientRequest extends FormRequest
{
    use NormalizesInput, ValidatesAddress;

    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client
            ? $this->user()->can('update', $client)
            : $this->user()->can('create', Client::class);
    }

    protected function prepareForValidation(): void
    {
        $this->keepDigits('document', 'phone', 'mobile', 'zip_code');
        $this->uppercase('state');
    }

    public function rules(): array
    {
        $isCompany = $this->input('person_type') === PersonType::Company->value;

        return [
            'person_type' => ['required', Rule::enum(PersonType::class)],
            'name' => ['required', 'string', 'max:150'],
            'document' => [
                'required',
                'string',
                $isCompany ? new Cnpj : new Cpf,
                Rule::unique('clients', 'document')->withoutTrashed()->ignore($this->route('client')),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'digits_between:10,11'],
            'mobile' => ['nullable', 'digits_between:10,11'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            ...$this->addressRules(),
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
