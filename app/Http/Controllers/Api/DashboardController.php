<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, DashboardService $dashboard): JsonResponse
    {
        return response()->json([
            'data' => $dashboard->metrics($request->expiringWithinDays()),
        ]);
    }
}
