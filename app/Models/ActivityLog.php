<?php

namespace App\Models;

use App\Enums\ActivityAction;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'subject_type', 'subject_id', 'action', 'changes'])]
class ActivityLog extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
            'changes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A auditoria de usuários revela quem teve perfil ou acesso alterado, por
     * isso só é visível para administradores, que são os únicos a gerir usuários.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->when(! $user->isAdmin(), fn (Builder $query) => $query->where('subject_type', '<>', 'user'));
    }

    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['subject_type'] ?? null, fn (Builder $query, string $type) => $query->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('subject_id', $id))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('user_id', $id))
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action));
    }
}
