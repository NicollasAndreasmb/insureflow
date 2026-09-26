<?php

namespace App\Services;

use App\Enums\ClaimStatus;
use App\Enums\InstallmentStatus;
use App\Enums\PolicyStatus;
use App\Models\Claim;
use App\Models\Client;
use App\Models\InsuranceProduct;
use App\Models\Installment;
use App\Models\Policy;
use BackedEnum;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores do dashboard, calculados inteiramente no PostgreSQL.
 *
 * Cada bloco é uma única consulta agregada (COUNT/SUM com FILTER), então o
 * custo não cresce com o número de registros carregados em PHP: o dashboard
 * inteiro executa um número fixo de queries. Os valores de status vêm dos
 * enums (via bindings), e registros excluídos logicamente são ignorados pelos
 * scopes de SoftDeletes. Valores monetários permanecem strings decimais.
 */
class DashboardService
{
    public function metrics(int $expiringWithinDays): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'expiring_window_days' => $expiringWithinDays,
            'clients' => $this->clients(),
            'policies' => $this->policies($expiringWithinDays),
            'premiums' => $this->premiums(),
            'installments' => $this->installments(),
            'claims' => $this->claims(),
            'policies_by_product' => $this->policiesByProduct(),
            'upcoming_expirations' => $this->upcomingExpirations($expiringWithinDays),
        ];
    }

    private function clients(): array
    {
        $row = Client::query()->toBase()
            ->selectRaw('count(*) as total, count(*) filter (where is_active) as active')
            ->first();

        return ['total' => (int) $row->total, 'active' => (int) $row->active];
    }

    private function policies(int $expiringWithinDays): array
    {
        $row = Policy::query()->toBase()
            ->selectRaw(
                'count(*) as total,
                 count(*) filter (where status = ?) as active,
                 count(*) filter (where status = ? and ends_on between ? and ?) as expiring_soon',
                [
                    PolicyStatus::Active->value,
                    PolicyStatus::Active->value, today()->toDateString(), today()->addDays($expiringWithinDays)->toDateString(),
                ],
            )
            ->first();

        $byStatus = Policy::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'total' => (int) $row->total,
            'active' => (int) $row->active,
            'expiring_soon' => (int) $row->expiring_soon,
            'by_status' => $this->countsByStatus(PolicyStatus::cases(), $byStatus),
        ];
    }

    private function premiums(): array
    {
        $row = Policy::query()->toBase()
            ->selectRaw(
                'coalesce(sum(premium) filter (where status = ?), 0) as active_total,
                 coalesce(sum(commission_amount) filter (where status = ?), 0) as active_commission_total',
                [PolicyStatus::Active->value, PolicyStatus::Active->value],
            )
            ->first();

        return [
            'active_total' => $this->money($row->active_total),
            'active_commission_total' => $this->money($row->active_commission_total),
        ];
    }

    private function installments(): array
    {
        $today = today()->toDateString();
        $pending = InstallmentStatus::Pending->value;

        $row = Installment::query()->toBase()
            ->join('policies', 'policies.id', '=', 'installments.policy_id')
            ->whereNull('policies.deleted_at')
            ->selectRaw(
                'count(*) filter (where installments.status = ? and due_date >= ?) as pending_count,
                 coalesce(sum(amount) filter (where installments.status = ? and due_date >= ?), 0) as pending_amount,
                 count(*) filter (where installments.status = ? and due_date < ?) as overdue_count,
                 coalesce(sum(amount) filter (where installments.status = ? and due_date < ?), 0) as overdue_amount,
                 count(*) filter (where installments.status = ?) as paid_count,
                 coalesce(sum(amount) filter (where installments.status = ?), 0) as paid_amount,
                 count(*) filter (where installments.status = ?) as cancelled_count,
                 coalesce(sum(amount) filter (where installments.status = ?), 0) as cancelled_amount',
                [
                    $pending, $today, $pending, $today,
                    $pending, $today, $pending, $today,
                    InstallmentStatus::Paid->value, InstallmentStatus::Paid->value,
                    InstallmentStatus::Cancelled->value, InstallmentStatus::Cancelled->value,
                ],
            )
            ->first();

        return collect(['pending', 'overdue', 'paid', 'cancelled'])
            ->mapWithKeys(fn (string $status) => [$status => [
                'label' => InstallmentStatus::from($status)->label(),
                'count' => (int) $row->{"{$status}_count"},
                'amount' => $this->money($row->{"{$status}_amount"}),
            ]])
            ->all();
    }

    private function claims(): array
    {
        $byStatus = Claim::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $open = collect(ClaimStatus::nonFinal())->sum(fn (ClaimStatus $status) => (int) ($byStatus[$status->value] ?? 0));

        return [
            'total' => (int) $byStatus->sum(),
            'open' => $open,
            'by_status' => $this->countsByStatus(ClaimStatus::cases(), $byStatus),
        ];
    }

    private function policiesByProduct(): array
    {
        return InsuranceProduct::query()->toBase()
            ->leftJoin('policies', function (JoinClause $join) {
                $join->on('policies.insurance_product_id', '=', 'insurance_products.id')->whereNull('policies.deleted_at');
            })
            ->groupBy('insurance_products.id', 'insurance_products.name')
            ->selectRaw(
                'insurance_products.id as product_id,
                 insurance_products.name,
                 count(policies.id) as policies_count,
                 count(policies.id) filter (where policies.status = ?) as active_count,
                 coalesce(sum(policies.premium) filter (where policies.status = ?), 0) as active_premium',
                [PolicyStatus::Active->value, PolicyStatus::Active->value],
            )
            ->orderByDesc('policies_count')
            ->orderBy('insurance_products.name')
            ->get()
            ->map(fn (object $row) => [
                'product_id' => (int) $row->product_id,
                'name' => $row->name,
                'policies_count' => (int) $row->policies_count,
                'active_count' => (int) $row->active_count,
                'active_premium' => $this->money($row->active_premium),
            ])
            ->all();
    }

    private function upcomingExpirations(int $expiringWithinDays): array
    {
        return Policy::query()
            ->with(['client', 'product'])
            ->expiringWithin($expiringWithinDays)
            ->orderBy('ends_on')
            ->limit(10)
            ->get()
            ->map(fn (Policy $policy) => [
                'id' => $policy->id,
                'number' => $policy->number,
                'client' => ['id' => $policy->client->id, 'name' => $policy->client->name],
                'product' => ['id' => $policy->product->id, 'name' => $policy->product->name],
                'ends_on' => $policy->ends_on->toDateString(),
                'days_left' => (int) today()->diffInDays($policy->ends_on),
                'premium' => $policy->premium,
            ])
            ->all();
    }

    /**
     * @param  list<BackedEnum>  $cases
     */
    private function countsByStatus(array $cases, Collection $counts): array
    {
        return array_map(fn (BackedEnum $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0),
        ], $cases);
    }

    private function money(string|int|float $value): string
    {
        return bcadd((string) $value, '0', 2);
    }
}
