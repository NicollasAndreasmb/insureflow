<?php

namespace App\Http\Requests\InsuranceProduct;

use App\Http\Requests\ListRequest;

class ListInsuranceProductsRequest extends ListRequest
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
        return ['name', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
