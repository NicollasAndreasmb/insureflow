<?php

namespace App\Http\Requests\Policy;

use App\Enums\PolicyStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListPoliciesRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::enum(PolicyStatus::class)],
            'client_id' => ['sometimes', 'integer'],
            'insurer_id' => ['sometimes', 'integer'],
            'insurance_product_id' => ['sometimes', 'integer'],
            'broker_id' => ['sometimes', 'integer'],
            'expiring_within_days' => ['sometimes', 'integer', 'between:1,365'],
        ];
    }

    protected function sortable(): array
    {
        return ['number', 'starts_on', 'ends_on', 'premium', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return '-starts_on';
    }
}
