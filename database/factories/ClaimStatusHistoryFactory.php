<?php

namespace Database\Factories;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\ClaimStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClaimStatusHistory>
 */
class ClaimStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'claim_id' => Claim::factory(),
            'from_status' => null,
            'to_status' => ClaimStatus::Open,
            'user_id' => null,
            'notes' => null,
        ];
    }
}
