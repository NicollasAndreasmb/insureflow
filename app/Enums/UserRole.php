<?php

namespace App\Enums;

/**
 * Perfis de acesso dos usuários internos da corretora.
 *
 * O perfil é gravado em users.role e será consultado pelas Policies do Laravel
 * para autorização. Novos perfis exigem um novo case aqui e uma migration que
 * atualize a CHECK constraint da coluna.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Broker = 'broker';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Broker => 'Corretor',
            self::Assistant => 'Assistente',
        };
    }
}
