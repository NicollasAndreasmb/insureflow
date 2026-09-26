<?php

namespace App\Models;

use App\Enums\PersonType;
use App\Models\Concerns\HasActivityLog;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    use HasActivityLog, HasFactory, SoftDeletes;

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

    public function policies(): HasMany
    {
        return $this->hasMany(Policy::class);
    }

    public function claims(): HasManyThrough
    {
        return $this->hasManyThrough(Claim::class, Policy::class);
    }

    public function installments(): HasManyThrough
    {
        return $this->hasManyThrough(Installment::class, Policy::class);
    }
}
