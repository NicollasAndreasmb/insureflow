<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
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
