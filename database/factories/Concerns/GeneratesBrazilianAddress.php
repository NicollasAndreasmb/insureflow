<?php

namespace Database\Factories\Concerns;

trait GeneratesBrazilianAddress
{
    protected function brazilianAddress(): array
    {
        return [
            'zip_code' => $this->digitsOnly(fake()->postcode()),
            'street' => fake()->streetName(),
            'number' => fake()->buildingNumber(),
            'complement' => fake()->optional(0.3)->secondaryAddress(),
            'district' => fake()->randomElement(['Centro', 'Jardim América', 'Vila Nova', 'Boa Vista', 'Santa Cecília', 'Bela Vista', 'Parque Industrial']),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
        ];
    }

    protected function digitsOnly(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }
}
