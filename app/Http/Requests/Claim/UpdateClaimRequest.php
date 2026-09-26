<?php

namespace App\Http\Requests\Claim;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edição dos dados descritivos do sinistro. Apólice, datas e protocolo são
 * fixos após a abertura, e o status muda apenas pelo endpoint de transição,
 * que registra o histórico.
 */
class UpdateClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('claim'));
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'required', 'string', 'max:5000'],
            'estimated_amount' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'policy_id' => ['prohibited'],
            'protocol' => ['prohibited'],
            'status' => ['prohibited'],
            'approved_amount' => ['prohibited'],
            'occurred_on' => ['prohibited'],
            'reported_on' => ['prohibited'],
        ];
    }
}
