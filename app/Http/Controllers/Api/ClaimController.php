<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Claim\ListClaimsRequest;
use App\Http\Requests\Claim\StoreClaimRequest;
use App\Http\Requests\Claim\UpdateClaimRequest;
use App\Http\Resources\ClaimResource;
use App\Models\Claim;
use App\Services\ClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ClaimController extends Controller
{
    public function __construct(private readonly ClaimService $claims) {}

    public function index(ListClaimsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Claim::class);

        $claims = Claim::query()
            ->with(['policy.client', 'policy.product'])
            ->filter($request->filters())
            ->orderBy(...$request->sort())
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return ClaimResource::collection($claims);
    }

    public function store(StoreClaimRequest $request): JsonResponse
    {
        $claim = $this->claims->open($request->validated(), $request->user());

        return (new ClaimResource($this->loadDetails($claim)))->response()->setStatusCode(201);
    }

    public function show(Claim $claim): ClaimResource
    {
        Gate::authorize('view', $claim);

        return new ClaimResource($this->loadDetails($claim));
    }

    public function update(UpdateClaimRequest $request, Claim $claim): ClaimResource
    {
        $claim = $this->claims->update($claim, $request->validated());

        return new ClaimResource($this->loadDetails($claim));
    }

    public function destroy(Claim $claim): Response
    {
        Gate::authorize('delete', $claim);

        $claim->delete();

        return response()->noContent();
    }

    private function loadDetails(Claim $claim): Claim
    {
        return $claim->load(['policy.client', 'policy.product', 'policy.insurer', 'statusHistory.user']);
    }
}
