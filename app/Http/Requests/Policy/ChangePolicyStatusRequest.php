<?php

namespace App\Http\Requests\Policy;

use App\Enums\PolicyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePolicyStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('policy'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PolicyStatus::class)],
        ];
    }

    public function status(): PolicyStatus
    {
        return PolicyStatus::from($this->validated('status'));
    }
}
