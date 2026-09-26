<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Policy\ChangePolicyStatusRequest;
use App\Http\Resources\PolicyResource;
use App\Models\Policy;
use App\Services\PolicyService;

class PolicyStatusController extends Controller
{
    public function __invoke(ChangePolicyStatusRequest $request, Policy $policy, PolicyService $policies): PolicyResource
    {
        $policy = $policies->changeStatus($policy, $request->status());

        return new PolicyResource($policy->load(['client', 'insurer', 'product', 'installments']));
    }
}
