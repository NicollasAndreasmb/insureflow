<?php

namespace App\Http\Requests\Policy;

use App\Http\Requests\Policy\Concerns\PolicyRules;
use App\Models\Policy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edição parcial (PATCH/PUT): apenas os campos enviados são validados e
 * alterados. O status muda somente pelo endpoint de transição, e cliente,
 * seguradora e modalidade não precisam estar ativos para manter o vínculo
 * existente.
 */
class UpdatePolicyRequest extends FormRequest
{
    use PolicyRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->policy());
    }

    public function rules(): array
    {
        $policy = $this->policy();
        $rules = $this->policyFieldRules((int) $this->input('insurer_id', $policy->insurer_id), $policy->id);

        foreach (['client_id' => 'clients', 'insurer_id' => 'insurers', 'insurance_product_id' => 'insurance_products'] as $field => $table) {
            $rules[$field] = ['required', 'integer', "exists:{$table},id,deleted_at,NULL"];
        }

        return [
            ...collect($rules)->map(fn (array $fieldRules) => ['sometimes', ...$fieldRules])->all(),
            'starts_on' => ['sometimes', 'required', 'date', 'before:'.$this->input('ends_on', $policy->ends_on->toDateString())],
            'ends_on' => ['sometimes', 'required', 'date', 'after:'.$this->input('starts_on', $policy->starts_on->toDateString())],
            'status' => ['prohibited'],
            'coverages' => ['prohibited'],
        ];
    }

    private function policy(): Policy
    {
        return $this->route('policy');
    }
}
