<?php

namespace App\Models;

use App\Enums\PersonType;
use App\Models\Concerns\HasActivityLog;
use App\Models\Concerns\HasPolicies;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'person_type', 'name', 'document', 'email', 'phone', 'mobile', 'birth_date',
    'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state',
    'notes', 'is_active',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasActivityLog, HasFactory, HasPolicies, SoftDeletes;

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'person_type' => PersonType::class,
            'birth_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function claims(): HasManyThrough
    {
        return $this->hasManyThrough(Claim::class, Policy::class);
    }

    public function installments(): HasManyThrough
    {
        return $this->hasManyThrough(Installment::class, Policy::class);
    }

    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, function (Builder $query, string $term) {
                $digits = preg_replace('/\D/', '', $term);

                $query->where(fn (Builder $query) => $query
                    ->whereAny(['name', 'email'], 'ilike', "%{$term}%")
                    ->when($digits !== '', fn (Builder $query) => $query->orWhere('document', 'like', "%{$digits}%")));
            })
            ->when($filters['person_type'] ?? null, fn (Builder $query, string $type) => $query->where('person_type', $type))
            ->when(isset($filters['is_active']), fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when($filters['state'] ?? null, fn (Builder $query, string $state) => $query->where('state', $state))
            ->when($filters['city'] ?? null, fn (Builder $query, string $city) => $query->where('city', 'ilike', "%{$city}%"));
    }
}
