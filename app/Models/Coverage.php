<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLog;
use Database\Factories\CoverageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['policy_id', 'name', 'description', 'coverage_limit', 'deductible', 'additional_premium', 'is_active'])]
class Coverage extends Model
{
    /** @use HasFactory<CoverageFactory> */
    use HasActivityLog, HasFactory;

    protected $attributes = [
        'additional_premium' => '0.00',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'coverage_limit' => 'decimal:2',
            'deductible' => 'decimal:2',
            'additional_premium' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }
}
