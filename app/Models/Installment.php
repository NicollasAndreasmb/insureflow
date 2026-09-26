<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
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
    use HasFactory;

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

    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', InstallmentStatus::Pending)->where('due_date', '<', today());
    }
}
