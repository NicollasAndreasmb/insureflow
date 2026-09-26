<?php

namespace App\Http\Requests\Insurer;

use App\Http\Requests\Concerns\NormalizesInput;
use App\Http\Requests\Concerns\ValidatesAddress;
use App\Models\Insurer;
use App\Rules\Cnpj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInsurerRequest extends FormRequest
{
    use NormalizesInput, ValidatesAddress;

    public function authorize(): bool
    {
        $insurer = $this->route('insurer');

        return $insurer instanceof Insurer
            ? $this->user()->can('update', $insurer)
            : $this->user()->can('create', Insurer::class);
    }

    protected function prepareForValidation(): void
    {
        $this->keepDigits('cnpj', 'phone', 'zip_code');
        $this->uppercase('state');
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:200'],
            'trade_name' => ['required', 'string', 'max:150'],
            'cnpj' => [
                'required',
                'string',
                new Cnpj,
                Rule::unique('insurers', 'cnpj')->withoutTrashed()->ignore($this->route('insurer')),
            ],
            'phone' => ['nullable', 'digits_between:10,11'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            ...$this->addressRules(),
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
