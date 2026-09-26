<?php

namespace Database\Seeders;

use App\Enums\ClaimStatus;
use App\Enums\PolicyStatus;
use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\Policy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Gera sinistros em apólices que estavam em vigor na data da ocorrência.
 *
 * O status final de cada sinistro depende de quão antigo ele é (recentes ainda
 * estão abertos ou em análise; antigos já foram decididos), e o histórico de
 * status reproduz um caminho válido segundo ClaimStatus::allowedTransitions().
 * Os protocolos são sequenciais por ano, na ordem de comunicação.
 */
class ClaimSeeder extends Seeder
{
    private const CLAIM_COUNT = 45;

    private const ESTIMATE_RANGES = [
        'seguro-auto' => [3000, 45000],
        'seguro-residencial' => [1000, 20000],
        'seguro-de-vida' => [20000, 200000],
        'seguro-empresarial' => [10000, 150000],
        'seguro-viagem' => [500, 15000],
    ];

    private const DESCRIPTIONS = [
        'seguro-auto' => ['Colisão traseira em via urbana.', 'Veículo furtado em estacionamento público.', 'Danos causados por alagamento.', 'Quebra de para-brisa por pedra na rodovia.'],
        'seguro-residencial' => ['Curto-circuito danificou eletrodomésticos.', 'Infiltração causada por vendaval.', 'Arrombamento com furto de equipamentos.'],
        'seguro-de-vida' => ['Internação prolongada por acidente.', 'Solicitação de indenização por invalidez parcial.'],
        'seguro-empresarial' => ['Incêndio parcial no depósito.', 'Queima de equipamentos por descarga elétrica.', 'Roubo de mercadorias em trânsito.'],
        'seguro-viagem' => ['Atendimento médico de emergência no exterior.', 'Extravio de bagagem em conexão.', 'Cancelamento de voo por problema de saúde.'],
    ];

    private CarbonImmutable $today;

    public function run(): void
    {
        $this->today = CarbonImmutable::today();

        $handlers = User::whereIn('role', [UserRole::Broker, UserRole::Assistant])->where('is_active', true)->get();

        $claims = Policy::with('product')
            ->whereIn('status', [PolicyStatus::Active, PolicyStatus::Expired, PolicyStatus::Suspended, PolicyStatus::Cancelled])
            ->where('starts_on', '<', $this->today->subDays(2))
            ->get()
            ->shuffle(fake()->randomNumber())
            ->take(self::CLAIM_COUNT)
            ->map(fn (Policy $policy) => $this->claimAttributes($policy))
            ->sortBy('reported_on')
            ->values();

        $sequenceByYear = [];

        foreach ($claims as $attributes) {
            $year = $attributes['reported_on']->year;
            $sequenceByYear[$year] = ($sequenceByYear[$year] ?? 0) + 1;

            $this->createClaim($attributes, sprintf('SIN-%d-%06d', $year, $sequenceByYear[$year]), $handlers);
        }
    }

    private function claimAttributes(Policy $policy): array
    {
        $coverageEnd = CarbonImmutable::parse($policy->ends_on)->min($this->today->subDay());
        $occurredOn = CarbonImmutable::parse($policy->starts_on)->addDays(
            fake()->numberBetween(0, (int) CarbonImmutable::parse($policy->starts_on)->diffInDays($coverageEnd)),
        );
        $reportedOn = $occurredOn->addDays(fake()->numberBetween(0, 7))->min($this->today);
        $slug = $policy->product->slug;

        return [
            'policy_id' => $policy->id,
            'occurred_on' => $occurredOn,
            'reported_on' => $reportedOn,
            'description' => fake()->randomElement(self::DESCRIPTIONS[$slug]),
            'estimated_amount' => bcdiv((string) fake()->numberBetween(...array_map(fn (int $v) => $v * 100, self::ESTIMATE_RANGES[$slug])), '100', 2),
        ];
    }

    private function createClaim(array $attributes, string $protocol, Collection $handlers): void
    {
        $path = $this->statusPath($attributes['reported_on']);
        $finalStatus = end($path);
        $wasApproved = in_array(ClaimStatus::Approved, $path, true);

        $now = now()->toImmutable();
        $moments = [$attributes['reported_on']->setTime(fake()->numberBetween(8, 17), fake()->numberBetween(0, 59))->min($now)];
        foreach (array_slice($path, 1) as $_) {
            $moments[] = end($moments)->addDays(fake()->numberBetween(1, 6))->addHours(fake()->numberBetween(1, 5))->min($now);
        }

        $claim = Claim::factory()->create([
            ...$attributes,
            'protocol' => $protocol,
            'status' => $finalStatus,
            'approved_amount' => $wasApproved
                ? bcdiv(bcmul($attributes['estimated_amount'], (string) fake()->numberBetween(60, 100), 2), '100', 2)
                : null,
            'notes' => $finalStatus === ClaimStatus::PendingDocuments ? 'Aguardando boletim de ocorrência e orçamentos.' : null,
            'created_at' => $moments[0],
            'updated_at' => end($moments),
        ]);

        $previous = null;
        foreach ($path as $index => $status) {
            $claim->statusHistory()->create([
                'from_status' => $previous,
                'to_status' => $status,
                'user_id' => $handlers->random()->id,
                'notes' => $index === 0 ? 'Sinistro comunicado pelo segurado.' : null,
                'created_at' => $moments[$index],
            ]);
            $previous = $status;
        }
    }

    /**
     * @return list<ClaimStatus>
     */
    private function statusPath(CarbonImmutable $reportedOn): array
    {
        $isRecent = $reportedOn->diffInDays($this->today) <= 10;

        $finalStatus = $isRecent
            ? fake()->randomElement([ClaimStatus::Open, ClaimStatus::UnderReview, ClaimStatus::PendingDocuments])
            : fake()->randomElement([
                ClaimStatus::UnderReview, ClaimStatus::PendingDocuments,
                ClaimStatus::Approved, ClaimStatus::Approved, ClaimStatus::Denied,
                ClaimStatus::Closed, ClaimStatus::Closed, ClaimStatus::Closed,
            ]);

        return match ($finalStatus) {
            ClaimStatus::Open => [ClaimStatus::Open],
            ClaimStatus::UnderReview => [ClaimStatus::Open, ClaimStatus::UnderReview],
            ClaimStatus::PendingDocuments => [ClaimStatus::Open, ClaimStatus::UnderReview, ClaimStatus::PendingDocuments],
            ClaimStatus::Approved => [ClaimStatus::Open, ClaimStatus::UnderReview, ClaimStatus::Approved],
            ClaimStatus::Denied => [ClaimStatus::Open, ClaimStatus::UnderReview, ClaimStatus::Denied],
            ClaimStatus::Closed => [
                ClaimStatus::Open, ClaimStatus::UnderReview,
                fake()->boolean(75) ? ClaimStatus::Approved : ClaimStatus::Denied,
                ClaimStatus::Closed,
            ],
        };
    }
}
