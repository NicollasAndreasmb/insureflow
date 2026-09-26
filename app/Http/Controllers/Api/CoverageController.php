<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coverage\SaveCoverageRequest;
use App\Http\Resources\CoverageResource;
use App\Models\Coverage;
use App\Models\Policy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Coberturas como recurso aninhado à apólice (rotas "shallow"): listagem e
 * criação em /policies/{policy}/coverages, demais operações em /coverages/{id}.
 * Coberturas de apólices canceladas ou expiradas não podem ser alteradas.
 */
class CoverageController extends Controller
{
    public function index(Policy $policy): AnonymousResourceCollection
    {
        Gate::authorize('view', $policy);

        return CoverageResource::collection($policy->coverages()->orderBy('name')->get());
    }

    public function store(SaveCoverageRequest $request, Policy $policy): JsonResponse
    {
        $policy->ensureIsEditable();

        $coverage = $policy->coverages()->create($request->validated());

        return (new CoverageResource($coverage))->response()->setStatusCode(201);
    }

    public function show(Coverage $coverage): CoverageResource
    {
        Gate::authorize('view', $coverage);

        return new CoverageResource($coverage);
    }

    public function update(SaveCoverageRequest $request, Coverage $coverage): CoverageResource
    {
        $request->targetPolicy()->ensureIsEditable();

        $coverage->update($request->validated());

        return new CoverageResource($coverage);
    }

    public function destroy(Coverage $coverage): Response
    {
        Gate::authorize('delete', $coverage);

        $coverage->policy->ensureIsEditable();
        $coverage->delete();

        return response()->noContent();
    }
}
