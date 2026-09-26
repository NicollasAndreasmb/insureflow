<?php

namespace App\Http\Requests\Claim;

use App\Enums\ClaimStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeClaimStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('claim'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ClaimStatus::class)],
            'approved_amount' => [
                'required_if:status,'.ClaimStatus::Approved->value,
                'prohibited_unless:status,'.ClaimStatus::Approved->value,
                'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function status(): ClaimStatus
    {
        return ClaimStatus::from($this->validated('status'));
    }

    public function approvedAmount(): ?string
    {
        $amount = $this->validated('approved_amount');

        return $amount === null ? null : (string) $amount;
    }
}
