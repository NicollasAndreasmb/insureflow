<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;

/**
 * Regras de acesso por perfil. Todas as Policies seguem a mesma matriz:
 *
 *   - consulta: qualquer usuário autenticado (a rota já exige conta ativa);
 *   - operação do dia a dia (clientes, sinistros, pagamentos): os três perfis;
 *   - carteira (apólices e coberturas): administrador e corretor;
 *   - catálogo (seguradoras, modalidades) e usuários: apenas administrador;
 *   - exclusões: restritas ao administrador, exceto clientes (também corretor).
 *
 * Regras que dependem do estado do registro (ex.: só excluir cotações) ficam
 * nos Services, não aqui: a Policy responde "quem pode", o Service "quando pode".
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Client $client): bool
    {
        return true;
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Broker);
    }
}
