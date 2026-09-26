<?php

namespace App\Http\Requests\Claim;

use App\Models\Claim;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida o formato dos dados do sinistro. A elegibilidade da apólice e a
 * ocorrência dentro da vigência dependem do estado da apólice e são
 * verificadas pelo ClaimService.
 */
class StoreClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Claim::class);
    }

    public function rules(): array
    {
        return [
            'policy_id' => ['required', 'integer', 'exists:policies,id,deleted_at,NULL'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'reported_on' => ['sometimes', 'date', 'after_or_equal:occurred_on', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:5000'],
            'estimated_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
