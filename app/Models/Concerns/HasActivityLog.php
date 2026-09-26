<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Expõe a trilha de auditoria (activity_logs) de uma entidade auditável.
 *
 * O model precisa estar registrado no morph map (AppServiceProvider). A gravação
 * automática dos registros será acoplada aqui quando a autenticação existir,
 * pois cada log precisa do usuário responsável pela operação.
 */
trait HasActivityLog
{
    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest('created_at')->latest('id');
    }
}
