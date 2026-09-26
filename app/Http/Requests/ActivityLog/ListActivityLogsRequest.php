<?php

namespace App\Http\Requests\ActivityLog;

use App\Enums\ActivityAction;
use App\Http\Requests\ListRequest;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\Rule;

/**
 * subject_type aceita os aliases do morph map (client, policy, claim...), e
 * não nomes de classe.
 */
class ListActivityLogsRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [
            'subject_type' => ['sometimes', Rule::in(array_keys(Relation::morphMap()))],
            'subject_id' => ['sometimes', 'integer', 'required_with:subject_type'],
            'user_id' => ['sometimes', 'integer'],
            'action' => ['sometimes', Rule::enum(ActivityAction::class)],
        ];
    }

    protected function sortable(): array
    {
        return ['created_at'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }
}
