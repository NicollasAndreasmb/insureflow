<?php

namespace Database\Factories;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\Policy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datas de ocorrência ficam nos últimos 20 dias, que sempre estão dentro da
 * vigência da apólice ativa gerada pela PolicyFactory.
 *
 * @extends Factory<Claim>
 */
class ClaimFactory extends Factory
{
    public function definition(): array
    {
        $occurredOn = today()->subDays(fake()->numberBetween(3, 20));

        return [
            'protocol' => 'SIN-'.$occurredOn->year.'-'.fake()->unique()->numerify('######'),
            'policy_id' => Policy::factory(),
            'occurred_on' => $occurredOn,
            'reported_on' => $occurredOn->copy()->addDays(fake()->numberBetween(0, 2)),
            'description' => fake()->paragraph(),
            'estimated_amount' => bcdiv((string) fake()->numberBetween(1_000_00, 50_000_00), '100', 2),
            'approved_amount' => null,
            'status' => ClaimStatus::Open,
            'notes' => null,
        ];
    }

    public function underReview(): static
    {
        return $this->state(['status' => ClaimStatus::UnderReview]);
    }

    public function pendingDocuments(): static
    {
        return $this->state(['status' => ClaimStatus::PendingDocuments]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClaimStatus::Approved,
            'approved_amount' => bcmul($attributes['estimated_amount'], '0.9', 2),
        ]);
    }

    public function denied(): static
    {
        return $this->state(['status' => ClaimStatus::Denied, 'approved_amount' => null]);
    }
}
