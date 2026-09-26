<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'expiring_within_days' => ['sometimes', 'integer', 'between:1,90'],
        ];
    }

    public function expiringWithinDays(): int
    {
        return (int) $this->validated('expiring_within_days', 30);
    }
}
