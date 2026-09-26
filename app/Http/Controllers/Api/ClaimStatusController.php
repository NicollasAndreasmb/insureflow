<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Claim\ChangeClaimStatusRequest;
use App\Http\Resources\ClaimResource;
use App\Models\Claim;
use App\Services\ClaimService;

class ClaimStatusController extends Controller
{
    public function __invoke(ChangeClaimStatusRequest $request, Claim $claim, ClaimService $claims): ClaimResource
    {
        $claim = $claims->changeStatus(
            $claim,
            $request->status(),
            $request->user(),
            $request->approvedAmount(),
            $request->validated('notes'),
        );

        return new ClaimResource($claim->load(['policy.client', 'statusHistory.user']));
    }
}
