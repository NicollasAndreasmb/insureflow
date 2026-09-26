<?php

namespace App\Http\Requests\InsuranceProduct;

use App\Models\InsuranceProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * O slug é derivado do nome quando não é informado, para que cadastrar uma
 * nova modalidade exija apenas o nome.
 */
class SaveInsuranceProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('insurance_product');

        return $product instanceof InsuranceProduct
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', InsuranceProduct::class);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && is_string($this->input('name'))) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $product = $this->route('insurance_product');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('insurance_products')->withoutTrashed()->ignore($product)],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('insurance_products')->withoutTrashed()->ignore($product)],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
