<?php

namespace App\Http\Requests\Insurer;

use App\Http\Requests\ListRequest;

class ListInsurersRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'in:0,1,true,false'],
        ];
    }

    protected function sortable(): array
    {
        return ['trade_name', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'trade_name';
    }
}
