<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityLog\ListActivityLogsRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Consulta da trilha de auditoria, usada no "histórico relacionado" das telas
 * de detalhe (ex.: ?subject_type=client&subject_id=15). Somente leitura: os
 * registros são gravados automaticamente pelos models.
 */
class ActivityLogController extends Controller
{
    public function index(ListActivityLogsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ActivityLog::class);

        $logs = ActivityLog::query()
            ->with('user')
            ->visibleTo($request->user())
            ->filter($request->filters())
            ->orderBy(...$request->sort())
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return ActivityLogResource::collection($logs);
    }
}
