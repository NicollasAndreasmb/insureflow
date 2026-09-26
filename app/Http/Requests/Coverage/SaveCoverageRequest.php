<?php

namespace App\Http\Requests\Coverage;

use App\Models\Coverage;
use App\Models\Policy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação (rota aninhada em /policies/{policy}/coverages) e edição (rota
 * /coverages/{coverage}) de coberturas. O nome é único dentro da apólice.
 */
class SaveCoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $coverage = $this->route('coverage');

        return $coverage instanceof Coverage
            ? $this->user()->can('update', $coverage)
            : $this->user()->can('create', Coverage::class);
    }

    public function rules(): array
    {
        $coverage = $this->route('coverage');
        $policyId = $coverage instanceof Coverage ? $coverage->policy_id : $this->route('policy')?->id;
        $money = ['numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'];

        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('coverages', 'name')->where('policy_id', $policyId)->ignore($coverage),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'coverage_limit' => ['required', ...$money],
            'deductible' => ['nullable', ...$money],
            'additional_premium' => ['sometimes', 'required', ...$money],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function targetPolicy(): Policy
    {
        $coverage = $this->route('coverage');

        return $coverage instanceof Coverage ? $coverage->policy : $this->route('policy');
    }
}
