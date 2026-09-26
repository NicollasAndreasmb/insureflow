<?php

namespace Database\Factories;

use App\Models\InsuranceProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InsuranceProduct>
 */
class InsuranceProductFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Seguro '.Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
