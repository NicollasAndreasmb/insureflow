<?php

namespace App\Http\Requests\Policy;

use App\Enums\PolicyStatus;
use App\Enums\UserRole;
use App\Http\Requests\Policy\Concerns\PolicyRules;
use App\Models\Policy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Uma apólice nasce como cotação, pendente ou já ativa (quando é cadastrada
 * depois de emitida pela seguradora); os demais status só são alcançados por
 * transição. Se o corretor não for informado, assume o usuário autenticado
 * quando ele for corretor.
 */
class StorePolicyRequest extends FormRequest
{
    use PolicyRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Policy::class);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('broker_id') && $this->user()->hasRole(UserRole::Broker)) {
            $this->merge(['broker_id' => $this->user()->id]);
        }
    }

    public function rules(): array
    {
        return [
            ...$this->policyFieldRules((int) $this->input('insurer_id')),
            'status' => ['sometimes', Rule::enum(PolicyStatus::class)->only(PolicyStatus::initial())],
            'coverages' => ['sometimes', 'array', 'max:20'],
            'coverages.*.name' => ['required', 'string', 'max:150', 'distinct'],
            'coverages.*.description' => ['nullable', 'string', 'max:2000'],
            'coverages.*.coverage_limit' => $this->moneyRules(),
            'coverages.*.deductible' => $this->moneyRules(nullable: true),
            'coverages.*.additional_premium' => ['sometimes', ...$this->moneyRules()],
        ];
    }
}
