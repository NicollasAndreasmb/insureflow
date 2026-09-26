<?php

namespace App\Console\Commands;

use App\Services\PolicyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Expira apólices ativas ou suspensas cuja vigência terminou.
 *
 * Agendado diariamente em routes/console.php e executado pelo container
 * "scheduler". É idempotente: rodar mais de uma vez no mesmo dia não altera
 * apólices já expiradas. Cada expiração passa pelas mesmas regras de transição
 * da API e fica registrada em activity_logs sem usuário (origem: sistema).
 */
#[Signature('policies:expire')]
#[Description('Marca como expiradas as apólices cuja vigência terminou')]
class ExpirePolicies extends Command
{
    public function handle(PolicyService $policies): int
    {
        $expired = $policies->expireEndedPolicies();

        $this->info("{$expired} apólice(s) expirada(s).");

        return self::SUCCESS;
    }
}
