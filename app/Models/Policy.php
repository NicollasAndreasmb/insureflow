<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PolicyStatus;
use App\Models\Concerns\HasActivityLog;
use Database\Factories\PolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Refreshes;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Apólice: entidade central do domínio, à qual se ligam coberturas, parcelas e sinistros.
 *
 * commission_amount não é preenchível: é uma coluna gerada pelo PostgreSQL a
 * partir de premium e commission_rate, e o atributo #[Refreshes] a relê do banco
 * após cada gravação para que o model nunca exponha um valor desatualizado.
 */
#[Fillable([
    'number', 'client_id', 'insurer_id', 'insurance_product_id', 'broker_id',
    'starts_on', 'ends_on', 'premium', 'deductible', 'commission_rate',
    'payment_method', 'installments_count', 'status', 'notes',
])]
#[Refreshes('commission_amount')]
class Policy extends Model
{
    /** @use HasFactory<PolicyFactory> */
    use HasActivityLog, HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'quote',
        'installments_count' => 1,
        'commission_rate' => '0.00',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'premium' => 'decimal:2',
            'deductible' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'installments_count' => 'integer',
            'status' => PolicyStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Insurer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InsuranceProduct::class, 'insurance_product_id');
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'broker_id');
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(Coverage::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('number');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', PolicyStatus::Active);
    }

    #[Scope]
    protected function expiringWithin(Builder $query, int $days): void
    {
        $query->where('status', PolicyStatus::Active)
            ->whereBetween('ends_on', [today(), today()->addDays($days)]);
    }
}
