<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Torna um model somente inserção na camada da aplicação.
 *
 * Complementa o trigger prevent_row_update() do PostgreSQL: o trigger protege
 * contra qualquer UPDATE, e este trait falha antes mesmo da query, com uma
 * mensagem clara, também para exclusões individuais de registros de auditoria.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(fn () => throw new LogicException(static::class.' records are append-only and cannot be updated.'));
        static::deleting(fn () => throw new LogicException(static::class.' records are append-only and cannot be deleted.'));
    }
}
