<?php

namespace Database\Factories;

use App\Models\Insurer;
use Database\Factories\Concerns\GeneratesBrazilianAddress;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Insurer>
 */
class InsurerFactory extends Factory
{
    use GeneratesBrazilianAddress;

    public function definition(): array
    {
        $tradeName = fake()->unique()->lastName().' Seguros';

        return [
            'legal_name' => $tradeName.' S.A.',
            'trade_name' => $tradeName,
            'cnpj' => fake()->unique()->cnpj(false),
            'phone' => '0800'.fake()->numerify('#######'),
            'email' => 'contato@'.Str::slug($tradeName).'.example',
            'website' => 'https://www.'.Str::slug($tradeName).'.example',
            ...$this->brazilianAddress(),
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
