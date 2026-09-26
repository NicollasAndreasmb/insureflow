<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Insurer\ListInsurersRequest;
use App\Http\Requests\Insurer\SaveInsurerRequest;
use App\Http\Resources\InsurerResource;
use App\Models\Insurer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InsurerController extends Controller
{
    public function index(ListInsurersRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Insurer::class);

        $insurers = Insurer::query()
            ->withCount('policies')
            ->filter($request->filters())
            ->orderBy(...$request->sort())
            ->paginate($request->perPage());

        return InsurerResource::collection($insurers);
    }

    public function store(SaveInsurerRequest $request): JsonResponse
    {
        $insurer = Insurer::create($request->validated());

        return (new InsurerResource($insurer))->response()->setStatusCode(201);
    }

    public function show(Insurer $insurer): InsurerResource
    {
        Gate::authorize('view', $insurer);

        return new InsurerResource($insurer->loadCount('policies'));
    }

    public function update(SaveInsurerRequest $request, Insurer $insurer): InsurerResource
    {
        $insurer->update($request->validated());

        return new InsurerResource($insurer);
    }

    public function destroy(Insurer $insurer): Response
    {
        Gate::authorize('delete', $insurer);

        $insurer->ensureHasNoOpenPolicies('A seguradora possui apólices em andamento. Inative-a em vez de excluí-la.');
        $insurer->delete();

        return response()->noContent();
    }
}
