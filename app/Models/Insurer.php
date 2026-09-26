<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLog;
use Database\Factories\InsurerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'legal_name', 'trade_name', 'cnpj', 'phone', 'email', 'website',
    'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state',
    'notes', 'is_active',
])]
class Insurer extends Model
{
    /** @use HasFactory<InsurerFactory> */
    use HasActivityLog, HasFactory, SoftDeletes;

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function policies(): HasMany
    {
        return $this->hasMany(Policy::class);
    }
}
