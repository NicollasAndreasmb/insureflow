<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerializesEnums;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Valores monetários são serializados como string ("1234.56") para não perder
 * precisão em ponto flutuante; a formatação em R$ é responsabilidade do frontend.
 *
 * @mixin \App\Models\Policy
 */
class PolicyResource extends JsonResource
{
    use SerializesEnums;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->enum($this->status),
            'allowed_transitions' => $this->enumList($this->status->allowedTransitions()),
            'is_editable' => $this->isEditable(),
            'is_deletable' => $this->isDeletable(),
            'client_id' => $this->client_id,
            'insurer_id' => $this->insurer_id,
            'insurance_product_id' => $this->insurance_product_id,
            'broker_id' => $this->broker_id,
            'client' => new ClientResource($this->whenLoaded('client')),
            'insurer' => new InsurerResource($this->whenLoaded('insurer')),
            'product' => new InsuranceProductResource($this->whenLoaded('product')),
            'broker' => new UserResource($this->whenLoaded('broker')),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'premium' => $this->premium,
            'deductible' => $this->deductible,
            'commission_rate' => $this->commission_rate,
            'commission_amount' => $this->commission_amount,
            'payment_method' => $this->enum($this->payment_method),
            'installments_count' => $this->installments_count,
            'notes' => $this->notes,
            'coverages' => CoverageResource::collection($this->whenLoaded('coverages')),
            'installments' => InstallmentResource::collection($this->whenLoaded('installments')),
            'claims' => ClaimResource::collection($this->whenLoaded('claims')),
            'claims_count' => $this->whenCounted('claims'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
