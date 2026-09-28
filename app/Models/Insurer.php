<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLog;
use App\Models\Concerns\HasPolicies;
use App\Support\Like;
use Database\Factories\InsurerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'legal_name', 'trade_name', 'cnpj', 'phone', 'email', 'website',
    'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state',
    'notes', 'is_active',
])]
class Insurer extends Model
{
    /** @use HasFactory<InsurerFactory> */
    use HasActivityLog, HasFactory, HasPolicies, SoftDeletes;

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, function (Builder $query, string $term) {
                $digits = preg_replace('/\D/', '', $term);

                $query->where(fn (Builder $query) => $query
                    ->whereAny(['trade_name', 'legal_name'], 'ilike', Like::contains($term))
                    ->when($digits !== '', fn (Builder $query) => $query->orWhere('cnpj', 'like', "%{$digits}%")));
            })
            ->when(isset($filters['is_active']), fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)));
    }
}
