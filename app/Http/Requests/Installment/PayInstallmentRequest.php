<?php

namespace App\Http\Requests\Installment;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class PayInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pay', $this->route('installment'));
    }

    public function rules(): array
    {
        return [
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function paidOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('paid_on'));
    }
}
