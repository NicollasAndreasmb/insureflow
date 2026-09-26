<?php

namespace App\Http\Requests\Concerns;

use App\Support\BrazilianStates;
use Illuminate\Validation\Rule;

trait ValidatesAddress
{
    protected function addressRules(): array
    {
        return [
            'zip_code' => ['nullable', 'digits:8'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', Rule::in(BrazilianStates::CODES)],
        ];
    }
}
