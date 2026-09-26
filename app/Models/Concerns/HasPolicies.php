<?php

namespace App\Models\Concerns;

use App\Enums\PolicyStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Policy;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comportamento comum às entidades das quais uma apólice depende (cliente,
 * seguradora, modalidade): elas não podem ser excluídas enquanto houver
 * apólices em andamento, apenas inativadas. Apólices canceladas ou expiradas
 * não bloqueiam a exclusão lógica, pois continuam acessíveis pelo histórico.
 */
trait HasPolicies
{
    public function policies(): HasMany
    {
        return $this->hasMany(Policy::class);
    }

    public function hasOpenPolicies(): bool
    {
        return $this->policies()->whereIn('status', PolicyStatus::nonFinal())->exists();
    }

    public function ensureHasNoOpenPolicies(string $message): void
    {
        if ($this->hasOpenPolicies()) {
            throw new BusinessRuleException($message);
        }
    }
}
