<?php

namespace App\Http\Requests\Claim;

use App\Enums\ClaimStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListClaimsRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::enum(ClaimStatus::class)],
            'open' => ['sometimes', 'in:0,1,true,false'],
            'policy_id' => ['sometimes', 'integer'],
            'client_id' => ['sometimes', 'integer'],
            'occurred_from' => ['sometimes', 'date'],
            'occurred_until' => ['sometimes', 'date', 'after_or_equal:occurred_from'],
        ];
    }

    protected function sortable(): array
    {
        return ['protocol', 'occurred_on', 'reported_on', 'estimated_amount'];
    }

    protected function defaultSort(): string
    {
        return '-reported_on';
    }
}
