<?php

namespace Database\Factories;

use App\Models\Coverage;
use App\Models\Policy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coverage>
 */
class CoverageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'policy_id' => Policy::factory(),
            'name' => fake()->randomElement(['Danos', 'Cobertura', 'Assistência', 'Proteção']).' '.fake()->unique()->words(2, true),
            'description' => fake()->optional(0.5)->sentence(),
            'coverage_limit' => bcdiv((string) fake()->numberBetween(10_000_00, 500_000_00), '100', 2),
            'deductible' => null,
            'additional_premium' => '0.00',
            'is_active' => true,
        ];
    }
}
