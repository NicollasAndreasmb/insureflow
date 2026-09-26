<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Models\Concerns\HasActivityLog;
use Database\Factories\ClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ClaimStatusHistory::class)->oldest('created_at')->oldest('id');
    }
}
