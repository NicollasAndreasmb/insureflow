<?php

namespace Database\Factories;

use App\Enums\InstallmentStatus;
use App\Models\Installment;
use App\Models\Policy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Installment>
 */
class InstallmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'policy_id' => Policy::factory(),
            'number' => 1,
            'amount' => bcdiv((string) fake()->numberBetween(100_00, 2_000_00), '100', 2),
            'due_date' => today()->addDays(fake()->numberBetween(1, 30)),
            'paid_on' => null,
            'status' => InstallmentStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => InstallmentStatus::Paid,
                'paid_on' => $attributes['due_date'],
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => InstallmentStatus::Pending,
            'due_date' => today()->subDays(fake()->numberBetween(1, 60)),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => InstallmentStatus::Cancelled]);
    }
}
