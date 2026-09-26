<?php

namespace App\Enums;

/**
 * Situação de uma parcela.
 *
 * Overdue nunca é gravado no banco (a CHECK constraint aceita apenas pending,
 * paid e cancelled): é derivado de uma parcela pendente cujo vencimento já
 * passou. Assim a informação nunca fica desatualizada por depender de um job
 * que não rodou. Veja Installment::effectiveStatus().
 */
enum InstallmentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Paid => 'Pago',
            self::Overdue => 'Atrasado',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * @return list<self>
     */
    public static function persistable(): array
    {
        return [self::Pending, self::Paid, self::Cancelled];
    }
}
