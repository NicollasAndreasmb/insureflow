<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Concerns\HasActivityLog;
use Carbon\CarbonInterface;
use Database\Factories\InstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parcela do prêmio de uma apólice.
 *
 * O atraso é derivado em tempo de leitura (pendente + vencimento anterior a
 * hoje) em vez de gravado, então effectiveStatus() e o scope overdue() são a
 * única fonte para "parcela atrasada" na aplicação.
 */
#[Fillable(['policy_id', 'number', 'amount', 'due_date', 'paid_on', 'status'])]
class Installment extends Model
{
    /** @use HasFactory<InstallmentFactory> */
    use HasActivityLog, HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_on' => 'date',
            'status' => InstallmentStatus::class,
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === InstallmentStatus::Pending && $this->due_date->lt(today());
    }

    public function effectiveStatus(): InstallmentStatus
    {
        return $this->isOverdue() ? InstallmentStatus::Overdue : $this->status;
    }

    public function isPayable(): bool
    {
        return $this->status === InstallmentStatus::Pending;
    }

    public function registerPayment(CarbonInterface $paidOn): void
    {
        if (! $this->isPayable()) {
            throw new BusinessRuleException(
                "Somente parcelas pendentes podem ser pagas. Esta parcela está {$this->status->label()}.",
                'status',
            );
        }

        $this->update(['status' => InstallmentStatus::Paid, 'paid_on' => $paidOn]);
    }

    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', InstallmentStatus::Pending)->where('due_date', '<', today());
    }

    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => match (InstallmentStatus::from($status)) {
                InstallmentStatus::Overdue => $query->overdue(),
                InstallmentStatus::Pending => $query->where('status', InstallmentStatus::Pending)->where('due_date', '>=', today()),
                default => $query->where('status', $status),
            })
            ->when($filters['policy_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('policy_id', $id))
            ->when($filters['client_id'] ?? null, fn (Builder $query, int|string $id) => $query->whereHas(
                'policy', fn (Builder $query) => $query->where('client_id', $id),
            ))
            ->when($filters['due_from'] ?? null, fn (Builder $query, string $date) => $query->where('due_date', '>=', $date))
            ->when($filters['due_until'] ?? null, fn (Builder $query, string $date) => $query->where('due_date', '<=', $date));
    }
}
