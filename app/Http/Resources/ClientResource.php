<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SerializesEnums;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
{
    use SerializesEnums;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'person_type' => $this->enum($this->person_type),
            'name' => $this->name,
            'document' => $this->document,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'birth_date' => $this->birth_date?->toDateString(),
            'zip_code' => $this->zip_code,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'district' => $this->district,
            'city' => $this->city,
            'state' => $this->state,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'policies_count' => $this->whenCounted('policies'),
            'claims_count' => $this->whenCounted('claims'),
            'policies' => PolicyResource::collection($this->whenLoaded('policies')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
