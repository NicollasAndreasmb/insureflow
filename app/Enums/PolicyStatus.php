<?php

namespace App\Enums;

/**
 * Ciclo de vida de uma apólice.
 *
 * As transições permitidas ficam centralizadas aqui para que qualquer camada
 * (services, validações, testes) consulte a mesma regra. Cancelada e Expirada
 * são estados finais: uma renovação gera uma nova apólice, preservando o
 * histórico da anterior.
 *
 *   quote -> pending -> active <-> suspended
 *                         |            |
 *                         +-> expired <+
 *   (qualquer estado não final) -> cancelled
 */
enum PolicyStatus: string
{
    case Quote = 'quote';
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Quote => 'Cotação',
            self::Pending => 'Pendente',
            self::Active => 'Ativa',
            self::Suspended => 'Suspensa',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Expirada',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Quote => [self::Pending, self::Cancelled],
            self::Pending => [self::Active, self::Cancelled],
            self::Active => [self::Suspended, self::Cancelled, self::Expired],
            self::Suspended => [self::Active, self::Cancelled, self::Expired],
            self::Cancelled, self::Expired => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * @return list<self>
     */
    public static function nonFinal(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => ! $status->isFinal()));
    }
}
