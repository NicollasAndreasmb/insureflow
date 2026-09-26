<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Models\Concerns\AppendOnly;
use Database\Factories\ClaimStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['claim_id', 'from_status', 'to_status', 'user_id', 'notes', 'created_at'])]
class ClaimStatusHistory extends Model
{
    /** @use HasFactory<ClaimStatusHistoryFactory> */
    use AppendOnly, HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => ClaimStatus::class,
            'to_status' => ClaimStatus::class,
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
