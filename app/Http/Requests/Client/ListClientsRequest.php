<?php

namespace App\Http\Requests\Client;

use App\Enums\PersonType;
use App\Http\Requests\ListRequest;
use App\Support\BrazilianStates;
use Illuminate\Validation\Rule;

class ListClientsRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'person_type' => ['sometimes', Rule::enum(PersonType::class)],
            'is_active' => ['sometimes', 'in:0,1,true,false'],
            'state' => ['sometimes', Rule::in(BrazilianStates::CODES)],
            'city' => ['sometimes', 'string', 'max:100'],
        ];
    }

    protected function sortable(): array
    {
        return ['name', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
