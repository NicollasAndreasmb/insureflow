<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Policy\ListPoliciesRequest;
use App\Http\Requests\Policy\StorePolicyRequest;
use App\Http\Requests\Policy\UpdatePolicyRequest;
use App\Http\Resources\PolicyResource;
use App\Models\Policy;
use App\Services\PolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PolicyController extends Controller
{
    public function __construct(private readonly PolicyService $policies) {}

    public function index(ListPoliciesRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Policy::class);

        $policies = Policy::query()
            ->with(['client', 'insurer', 'product'])
            ->filter($request->filters())
            ->orderBy(...$request->sort())
            ->paginate($request->perPage());

        return PolicyResource::collection($policies);
    }

    public function store(StorePolicyRequest $request): JsonResponse
    {
        $policy = $this->policies->create($request->validated());

        return (new PolicyResource($this->loadDetails($policy)))->response()->setStatusCode(201);
    }

    public function show(Policy $policy): PolicyResource
    {
        Gate::authorize('view', $policy);

        return new PolicyResource($this->loadDetails($policy));
    }

    public function update(UpdatePolicyRequest $request, Policy $policy): PolicyResource
    {
        $policy = $this->policies->update($policy, $request->validated());

        return new PolicyResource($this->loadDetails($policy));
    }

    public function destroy(Policy $policy): Response
    {
        Gate::authorize('delete', $policy);

        $this->policies->delete($policy);

        return response()->noContent();
    }

    private function loadDetails(Policy $policy): Policy
    {
        return $policy->load(['client', 'insurer', 'product', 'broker', 'coverages', 'installments', 'claims']);
    }
}
