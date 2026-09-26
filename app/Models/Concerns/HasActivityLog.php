<?php

namespace App\Models\Concerns;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * Trilha de auditoria automática (activity_logs) para entidades de negócio.
 *
 * Os eventos do Eloquent registram criação, alteração, exclusão e restauração.
 * Uma alteração que envolve a coluna status é registrada como status_changed,
 * então Services e Controllers não precisam lembrar de gravar logs. O usuário
 * é o autenticado na requisição, ou nulo quando a origem é o sistema (ex.:
 * expiração pelo scheduler).
 *
 * Apenas os campos alterados são guardados em changes ({old, new}); senhas,
 * tokens e timestamps técnicos nunca entram no log. Atualizações em massa via
 * query builder não disparam eventos e, portanto, não geram logs; os Services
 * só as usam para efeitos colaterais já registrados na entidade principal.
 */
trait HasActivityLog
{
    private const ACTIVITY_LOG_EXCLUDED = [
        'id', 'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at',
    ];

    public static function bootHasActivityLog(): void
    {
        static::created(fn (self $model) => $model->recordActivity(
            ActivityAction::Created,
            ['new' => Arr::except($model->getAttributes(), self::ACTIVITY_LOG_EXCLUDED)],
        ));

        static::updated(function (self $model) {
            $new = Arr::except($model->getChanges(), self::ACTIVITY_LOG_EXCLUDED);

            if ($new === []) {
                return;
            }

            $model->recordActivity(
                array_key_exists('status', $new) ? ActivityAction::StatusChanged : ActivityAction::Updated,
                ['old' => array_intersect_key($model->getRawOriginal(), $new), 'new' => $new],
            );
        });

        static::deleted(fn (self $model) => $model->recordActivity(ActivityAction::Deleted));

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(fn (self $model) => $model->recordActivity(ActivityAction::Restored));
        }
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest('created_at')->latest('id');
    }

    protected function recordActivity(ActivityAction $action, ?array $changes = null): void
    {
        $this->activities()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }
}
