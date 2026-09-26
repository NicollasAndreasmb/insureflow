<?php

namespace Database\Seeders;

use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PolicyStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\InsuranceProduct;
use App\Models\Insurer;
use App\Models\Policy;
use App\Models\User;
use App\Support\InstallmentSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Gera apólices com coberturas e parcelas coerentes com o status de cada uma.
 *
 * Cada modalidade tem um perfil (faixa de prêmio, comissão, franquia, vigência
 * e coberturas típicas). As datas são calculadas a partir do status: ativas
 * estão em vigor hoje, expiradas terminaram no passado, cotações começam no
 * futuro, e um grupo de ativas vence nos próximos 30 dias para alimentar o
 * painel de próximos vencimentos. As parcelas usam InstallmentSchedule e têm a
 * situação derivada do vencimento: vencidas pagas, futuras pendentes, e algumas
 * pendentes em atraso para apólices ativas e suspensas.
 */
class PolicySeeder extends Seeder
{
    private const PROFILES = [
        'seguro-auto' => [
            'weight' => 40,
            'premium' => [1800, 6500],
            'commission' => [15, 20],
            'deductible' => [1500, 6000],
            'coverages' => [
                ['Colisão, incêndio e roubo', 'Indenização integral ou parcial do veículo segurado.', [60000, 180000]],
                ['Danos materiais a terceiros', null, [100000, 300000]],
                ['Danos corporais a terceiros', null, [100000, 300000]],
                ['Assistência 24 horas', 'Guincho, chaveiro e socorro mecânico.', [5000, 5000]],
                ['Carro reserva', 'Até 15 diárias de carro reserva.', [3000, 6000]],
                ['Vidros, faróis e retrovisores', null, [4000, 8000]],
            ],
        ],
        'seguro-residencial' => [
            'weight' => 20,
            'premium' => [350, 1500],
            'commission' => [20, 30],
            'deductible' => [500, 2000],
            'coverages' => [
                ['Incêndio, raio e explosão', 'Cobertura básica do imóvel.', [200000, 800000]],
                ['Danos elétricos', null, [10000, 40000]],
                ['Roubo e furto qualificado', null, [15000, 60000]],
                ['Vendaval e granizo', null, [20000, 80000]],
                ['Responsabilidade civil familiar', null, [20000, 100000]],
            ],
        ],
        'seguro-de-vida' => [
            'weight' => 15,
            'premium' => [600, 3000],
            'commission' => [20, 35],
            'deductible' => null,
            'coverages' => [
                ['Morte natural ou acidental', 'Capital pago aos beneficiários.', [100000, 1000000]],
                ['Invalidez permanente por acidente', null, [100000, 1000000]],
                ['Doenças graves', null, [50000, 300000]],
                ['Assistência funeral', null, [7000, 15000]],
            ],
        ],
        'seguro-empresarial' => [
            'weight' => 10,
            'premium' => [5000, 40000],
            'commission' => [10, 20],
            'deductible' => [5000, 25000],
            'coverages' => [
                ['Incêndio, raio e explosão', 'Cobertura do prédio e do conteúdo.', [1000000, 8000000]],
                ['Lucros cessantes', 'Despesas fixas durante a paralisação.', [200000, 1500000]],
                ['Responsabilidade civil operações', null, [300000, 2000000]],
                ['Roubo de bens e mercadorias', null, [100000, 800000]],
                ['Equipamentos eletrônicos', null, [50000, 400000]],
            ],
        ],
        'seguro-viagem' => [
            'weight' => 15,
            'premium' => [150, 900],
            'commission' => [20, 30],
            'deductible' => null,
            'coverages' => [
                ['Despesas médicas e hospitalares', null, [30000, 300000]],
                ['Extravio de bagagem', null, [2000, 8000]],
                ['Cancelamento de viagem', null, [5000, 20000]],
                ['Repatriação sanitária', null, [30000, 100000]],
            ],
        ],
    ];

    private const STATUS_PLAN = [
        'expiring' => 12,
        'active' => 91,
        'expired' => 25,
        'pending' => 14,
        'quote' => 14,
        'cancelled' => 14,
        'suspended' => 10,
    ];

    private CarbonImmutable $today;

    public function run(): void
    {
        $this->today = CarbonImmutable::today();

        $products = InsuranceProduct::all()->keyBy('slug');
        $insurers = Insurer::all();
        $clients = Client::all();
        $brokers = User::where('role', UserRole::Broker)->where('is_active', true)->get();

        foreach (self::STATUS_PLAN as $plan => $quantity) {
            for ($i = 0; $i < $quantity; $i++) {
                $this->createPolicy($plan, $products, $insurers, $clients, $brokers);
            }
        }
    }

    private function createPolicy(string $plan, Collection $products, Collection $insurers, Collection $clients, Collection $brokers): void
    {
        $slug = $this->pickProductSlug(allowTravel: ! in_array($plan, ['expiring', 'suspended'], true));
        $profile = self::PROFILES[$slug];
        $status = $plan === 'expiring' ? PolicyStatus::Active : PolicyStatus::from($plan);
        $isHistorical = in_array($status, [PolicyStatus::Expired, PolicyStatus::Cancelled], true);

        [$startsOn, $endsOn] = $this->term($plan, $slug === 'seguro-viagem');
        $paymentMethod = fake()->randomElement(PaymentMethod::cases());
        $installmentsCount = $this->installmentsCount($paymentMethod, $slug === 'seguro-viagem');

        $policy = Policy::factory()->create([
            'client_id' => ($isHistorical ? $clients : $clients->where('is_active', true))->random()->id,
            'insurer_id' => ($status === PolicyStatus::Expired ? $insurers : $insurers->where('is_active', true))->random()->id,
            'insurance_product_id' => $products[$slug]->id,
            'broker_id' => $brokers->random()->id,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'premium' => $this->money(...$profile['premium']),
            'deductible' => $profile['deductible'] ? $this->money(...$profile['deductible']) : null,
            'commission_rate' => $this->commissionRate(...$profile['commission']),
            'payment_method' => $paymentMethod,
            'installments_count' => $installmentsCount,
            'status' => $status,
            'notes' => fake()->optional(0.15)->sentence(),
            'created_at' => $startsOn->min($this->today)->subDays(fake()->numberBetween(1, 15)),
        ]);

        $this->createCoverages($policy, $profile['coverages']);

        if ($status !== PolicyStatus::Quote) {
            $this->createInstallments($policy, $status);
        }
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function term(string $plan, bool $isTravel): array
    {
        $today = $this->today;
        $tripDays = fn () => fake()->numberBetween(7, 30);

        $startingOn = fn (CarbonImmutable $start) => [$start, $isTravel ? $start->addDays($tripDays()) : $start->addYear()];
        $endingOn = fn (CarbonImmutable $end) => [$isTravel ? $end->subDays($tripDays()) : $end->subYear(), $end];

        return match ($plan) {
            'expiring' => $endingOn($today->addDays(fake()->numberBetween(1, 30))),
            'active' => $startingOn($today->subDays($isTravel ? fake()->numberBetween(0, 5) : fake()->numberBetween(35, 330))),
            'expired' => $endingOn($today->subDays(fake()->numberBetween(1, 300))),
            'pending' => $startingOn($today->addDays(fake()->numberBetween(0, 20))),
            'quote' => $startingOn($today->addDays(fake()->numberBetween(5, 45))),
            'cancelled' => $startingOn($today->subDays(fake()->numberBetween(30, 300))),
            'suspended' => $startingOn($today->subDays(fake()->numberBetween(60, 300))),
        };
    }

    private function createCoverages(Policy $policy, array $templates): void
    {
        $mandatory = array_slice($templates, 0, 2);
        $optional = fake()->randomElements(array_slice($templates, 2), fake()->numberBetween(0, count($templates) - 2));

        $policy->coverages()->createMany(array_map(fn (array $template) => [
            'name' => $template[0],
            'description' => $template[1],
            'coverage_limit' => $this->money(...$template[2]),
            'deductible' => null,
            'additional_premium' => fake()->boolean(20) ? $this->money(50, 400) : '0.00',
        ], [...$mandatory, ...$optional]));
    }

    private function createInstallments(Policy $policy, PolicyStatus $status): void
    {
        $schedule = InstallmentSchedule::build($policy->premium, $policy->installments_count, $policy->starts_on);
        $pastDue = array_values(array_filter($schedule, fn (array $item) => $item['due_date']->lt($this->today)));
        $lastPastDueNumber = $pastDue === [] ? null : end($pastDue)['number'];

        $unpaidFromNumber = match (true) {
            $status === PolicyStatus::Suspended => max(1, ($lastPastDueNumber ?? 1) - fake()->numberBetween(0, 1)),
            $status === PolicyStatus::Active && fake()->boolean(12) => $lastPastDueNumber,
            default => null,
        };

        $policy->installments()->createMany(array_map(function (array $item) use ($status, $unpaidFromNumber) {
            $isPastDue = $item['due_date']->lt($this->today);
            $isLeftUnpaid = $unpaidFromNumber !== null && $item['number'] >= $unpaidFromNumber;

            $installmentStatus = match (true) {
                $status === PolicyStatus::Pending => InstallmentStatus::Pending,
                $isPastDue && ! $isLeftUnpaid => InstallmentStatus::Paid,
                $status === PolicyStatus::Cancelled => InstallmentStatus::Cancelled,
                default => InstallmentStatus::Pending,
            };

            return [
                ...$item,
                'status' => $installmentStatus,
                'paid_on' => $installmentStatus === InstallmentStatus::Paid
                    ? $item['due_date']->subDays(fake()->numberBetween(0, 5))
                    : null,
            ];
        }, $schedule));
    }

    private function pickProductSlug(bool $allowTravel): string
    {
        $weights = array_map(fn (array $profile) => $profile['weight'], self::PROFILES);

        if (! $allowTravel) {
            unset($weights['seguro-viagem']);
        }

        $pick = fake()->numberBetween(1, array_sum($weights));

        foreach ($weights as $slug => $weight) {
            if (($pick -= $weight) <= 0) {
                return $slug;
            }
        }

        return array_key_first($weights);
    }

    private function installmentsCount(PaymentMethod $method, bool $isTravel): int
    {
        if ($isTravel) {
            return fake()->numberBetween(1, 3);
        }

        return match ($method) {
            PaymentMethod::CreditCard => fake()->numberBetween(1, 12),
            PaymentMethod::DirectDebit => fake()->numberBetween(1, 10),
            PaymentMethod::BankSlip => fake()->numberBetween(1, 6),
            PaymentMethod::Pix => fake()->numberBetween(1, 4),
        };
    }

    private function money(int $min, int $max): string
    {
        return bcdiv((string) fake()->numberBetween($min * 100, $max * 100), '100', 2);
    }

    private function commissionRate(int $min, int $max): string
    {
        return bcdiv((string) fake()->numberBetween($min * 2, $max * 2), '2', 2);
    }
}
