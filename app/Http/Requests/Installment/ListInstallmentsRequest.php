<?php

namespace App\Http\Requests\Installment;

use App\Enums\InstallmentStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

/**
 * O filtro de status usa o status efetivo: "overdue" e "pending" são
 * mutuamente exclusivos, como exibido ao usuário.
 */
class ListInstallmentsRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(InstallmentStatus::class)],
            'policy_id' => ['sometimes', 'integer'],
            'client_id' => ['sometimes', 'integer'],
            'due_from' => ['sometimes', 'date'],
            'due_until' => ['sometimes', 'date', 'after_or_equal:due_from'],
        ];
    }

    protected function sortable(): array
    {
        return ['due_date', 'amount', 'paid_on'];
    }

    protected function defaultSort(): string
    {
        return 'due_date';
    }
}
