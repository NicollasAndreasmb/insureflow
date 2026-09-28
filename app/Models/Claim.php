<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Models\Concerns\HasActivityLog;
use App\Support\Like;
use Database\Factories\ClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sinistro comunicado sobre uma apólice.
 *
 * O cliente é resolvido através da apólice (client() é um HasOneThrough), sem
 * coluna client_id própria, garantindo que o sinistro sempre pertença ao
 * titular da apólice.
 */
#[Fillable([
    'protocol', 'policy_id', 'occurred_on', 'reported_on', 'description',
    'estimated_amount', 'approved_amount', 'status', 'notes',
])]
class Claim extends Model
{
    /** @use HasFactory<ClaimFactory> */
    use HasActivityLog, HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'reported_on' => 'date',
            'estimated_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'status' => ClaimStatus::class,
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    public function client(): HasOneThrough
    {
        return $this->hasOneThrough(Client::class, Policy::class, 'id', 'id', 'policy_id', 'client_id');
    }

    public function isEditable(): bool
    {
        return ! $this->status->isFinal();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ClaimStatusHistory::class)->oldest('created_at')->oldest('id');
    }

    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(
                fn (Builder $query) => $query
                    ->where('protocol', 'ilike', Like::contains($term))
                    ->orWhereHas('policy', fn (Builder $query) => $query
                        ->where('number', 'ilike', Like::contains($term))
                        ->orWhereHas('client', fn (Builder $query) => $query->where('name', 'ilike', Like::contains($term)))),
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(filter_var($filters['open'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query->whereIn('status', ClaimStatus::nonFinal()))
            ->when($filters['policy_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('policy_id', $id))
            ->when($filters['client_id'] ?? null, fn (Builder $query, int|string $id) => $query->whereHas(
                'policy', fn (Builder $query) => $query->where('client_id', $id),
            ))
            ->when($filters['occurred_from'] ?? null, fn (Builder $query, string $date) => $query->where('occurred_on', '>=', $date))
            ->when($filters['occurred_until'] ?? null, fn (Builder $query, string $date) => $query->where('occurred_on', '<=', $date));
    }
}
