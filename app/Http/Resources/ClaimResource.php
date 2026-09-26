<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerializesEnums;
use App\Models\Claim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Claim
 */
class ClaimResource extends JsonResource
{
    use SerializesEnums;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'protocol' => $this->protocol,
            'status' => $this->enum($this->status),
            'allowed_transitions' => $this->enumList($this->status->allowedTransitions()),
            'is_editable' => $this->isEditable(),
            'policy_id' => $this->policy_id,
            'policy' => new PolicyResource($this->whenLoaded('policy')),
            'client' => new ClientResource($this->whenLoaded('client')),
            'occurred_on' => $this->occurred_on?->toDateString(),
            'reported_on' => $this->reported_on?->toDateString(),
            'description' => $this->description,
            'estimated_amount' => $this->estimated_amount,
            'approved_amount' => $this->approved_amount,
            'notes' => $this->notes,
            'status_history' => ClaimStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
