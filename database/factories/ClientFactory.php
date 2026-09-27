<?php

namespace Database\Factories;

use App\Enums\PersonType;
use App\Models\Client;
use Database\Factories\Concerns\GeneratesBrazilianAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    use GeneratesBrazilianAddress;

    public function definition(): array
    {
        return [
            'person_type' => PersonType::Individual,
            'name' => fake()->name(),
            'document' => fake()->unique()->cpf(false),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional(0.4)->passthrough($this->landlineNumber()),
            'mobile' => $this->mobileNumber(),
            'birth_date' => fake()->dateTimeBetween('-75 years', '-18 years')->format('Y-m-d'),
            ...$this->brazilianAddress(),
            'notes' => fake()->optional(0.2)->sentence(),
            'is_active' => true,
        ];
    }

    public function individual(): static
    {
        return $this->state(fn () => [
            'person_type' => PersonType::Individual,
            'name' => fake()->name(),
            'document' => fake()->unique()->cpf(false),
            'birth_date' => fake()->dateTimeBetween('-75 years', '-18 years')->format('Y-m-d'),
        ]);
    }

    public function company(): static
    {
        return $this->state(fn () => [
            'person_type' => PersonType::Company,
            'name' => fake()->company(),
            'document' => fake()->unique()->cnpj(false),
            'phone' => $this->landlineNumber(),
            'birth_date' => fake()->dateTimeBetween('-40 years', '-1 year')->format('Y-m-d'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * O Faker pt_BR gera números sem DDD (9 ou 8 dígitos); a API exige DDD
     * (10 ou 11 dígitos), então o código de área é acrescentado aqui.
     */
    private function mobileNumber(): string
    {
        return fake()->areaCode().$this->digitsOnly(fake()->cellphone(false));
    }

    private function landlineNumber(): string
    {
        return fake()->areaCode().$this->digitsOnly(fake()->landline(false));
    }
}
