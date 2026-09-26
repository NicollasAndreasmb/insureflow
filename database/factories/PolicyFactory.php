<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PolicyStatus;
use App\Models\Client;
use App\Models\InsuranceProduct;
use App\Models\Insurer;
use App\Models\Policy;
use App\Models\User;
use App\Support\InstallmentSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por padrão gera uma apólice ativa com vigência anual iniciada há 1 a 11 meses,
 * de forma que datas de sinistro nos últimos dias caiam sempre dentro da vigência.
 *
 * @extends Factory<Policy>
 */
class PolicyFactory extends Factory
{
    public function definition(): array
    {
        $startsOn = today()->subMonths(fake()->numberBetween(1, 11))->subDays(fake()->numberBetween(0, 27));

        return [
            'number' => fake()->unique()->numerify('##.##.#######'),
            'client_id' => Client::factory(),
            'insurer_id' => Insurer::factory(),
            'insurance_product_id' => InsuranceProduct::factory(),
            'broker_id' => User::factory()->broker(),
            'starts_on' => $startsOn,
            'ends_on' => $startsOn->copy()->addYear(),
            'premium' => $this->money(500, 8000),
            'deductible' => fake()->optional(0.7)->passthrough($this->money(500, 5000)),
            'commission_rate' => fake()->randomElement(['10.00', '12.50', '15.00', '20.00', '25.00']),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'installments_count' => fake()->numberBetween(1, 12),
            'status' => PolicyStatus::Active,
            'notes' => null,
        ];
    }

    public function quote(): static
    {
        return $this->withStatus(PolicyStatus::Quote)->startingIn(fake()->numberBetween(5, 45));
    }

    public function pending(): static
    {
        return $this->withStatus(PolicyStatus::Pending)->startingIn(fake()->numberBetween(0, 20));
    }

    public function active(): static
    {
        return $this->withStatus(PolicyStatus::Active);
    }

    public function suspended(): static
    {
        return $this->withStatus(PolicyStatus::Suspended);
    }

    public function cancelled(): static
    {
        return $this->withStatus(PolicyStatus::Cancelled);
    }

    public function expired(): static
    {
        return $this->withStatus(PolicyStatus::Expired)->state(function () {
            $endsOn = today()->subDays(fake()->numberBetween(1, 300));

            return ['starts_on' => $endsOn->copy()->subYear(), 'ends_on' => $endsOn];
        });
    }

    public function expiringInDays(int $days): static
    {
        return $this->active()->state(function () use ($days) {
            $endsOn = today()->addDays($days);

            return ['starts_on' => $endsOn->copy()->subYear(), 'ends_on' => $endsOn];
        });
    }

    public function withStatus(PolicyStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    /**
     * Cria as parcelas a partir do prêmio, usando a mesma regra de divisão da aplicação.
     */
    public function withInstallments(): static
    {
        return $this->afterCreating(function (Policy $policy) {
            $schedule = InstallmentSchedule::build($policy->premium, $policy->installments_count, $policy->starts_on);

            $policy->installments()->createMany($schedule);
        });
    }

    private function startingIn(int $days): static
    {
        return $this->state(function () use ($days) {
            $startsOn = today()->addDays($days);

            return ['starts_on' => $startsOn, 'ends_on' => $startsOn->copy()->addYear()];
        });
    }

    private function money(int $min, int $max): string
    {
        return bcdiv((string) fake()->numberBetween($min * 100, $max * 100), '100', 2);
    }
}
