<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerializesEnums;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O status exposto é o efetivo (inclui "atrasado", que não é gravado no banco),
 * calculado por Installment::effectiveStatus().
 *
 * @mixin \App\Models\Installment
 */
class InstallmentResource extends JsonResource
{
    use SerializesEnums;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'policy_id' => $this->policy_id,
            'policy' => new PolicyResource($this->whenLoaded('policy')),
            'number' => $this->number,
            'amount' => $this->amount,
            'due_date' => $this->due_date?->toDateString(),
            'paid_on' => $this->paid_on?->toDateString(),
            'status' => $this->enum($this->effectiveStatus()),
            'is_overdue' => $this->isOverdue(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
