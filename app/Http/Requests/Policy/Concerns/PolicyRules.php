<?php

namespace App\Http\Requests\Policy\Concerns;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use Illuminate\Validation\Rule;

/**
 * Regras de campo compartilhadas entre criação e edição de apólices. As regras
 * que dependem do estado da apólice (vigência x status, parcelas pagas) ficam
 * no PolicyService.
 */
trait PolicyRules
{
    protected function moneyRules(bool $nullable = false): array
    {
        return [$nullable ? 'nullable' : 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'];
    }

    protected function policyFieldRules(?int $insurerId, ?int $ignorePolicyId = null): array
    {
        return [
            'number' => [
                'required', 'string', 'max:50',
                Rule::unique('policies', 'number')->where('insurer_id', $insurerId)->withoutTrashed()->ignore($ignorePolicyId),
            ],
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('is_active', true)->withoutTrashed()],
            'insurer_id' => ['required', 'integer', Rule::exists('insurers', 'id')->where('is_active', true)->withoutTrashed()],
            'insurance_product_id' => ['required', 'integer', Rule::exists('insurance_products', 'id')->where('is_active', true)->withoutTrashed()],
            'broker_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')
                    ->where('is_active', true)
                    ->whereIn('role', [UserRole::Admin->value, UserRole::Broker->value])
                    ->withoutTrashed(),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'premium' => $this->moneyRules(),
            'deductible' => $this->moneyRules(nullable: true),
            'commission_rate' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'installments_count' => ['required', 'integer', 'between:1,12'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
