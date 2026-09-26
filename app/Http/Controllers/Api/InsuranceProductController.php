<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InsuranceProduct\ListInsuranceProductsRequest;
use App\Http\Requests\InsuranceProduct\SaveInsuranceProductRequest;
use App\Http\Resources\InsuranceProductResource;
use App\Models\InsuranceProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InsuranceProductController extends Controller
{
    public function index(ListInsuranceProductsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InsuranceProduct::class);

        $products = InsuranceProduct::query()
            ->withCount('policies')
            ->filter($request->filters())
            ->orderBy(...$request->sort())
            ->paginate($request->perPage());

        return InsuranceProductResource::collection($products);
    }

    public function store(SaveInsuranceProductRequest $request): JsonResponse
    {
        $product = InsuranceProduct::create($request->validated());

        return (new InsuranceProductResource($product))->response()->setStatusCode(201);
    }

    public function show(InsuranceProduct $insuranceProduct): InsuranceProductResource
    {
        Gate::authorize('view', $insuranceProduct);

        return new InsuranceProductResource($insuranceProduct->loadCount('policies'));
    }

    public function update(SaveInsuranceProductRequest $request, InsuranceProduct $insuranceProduct): InsuranceProductResource
    {
        $insuranceProduct->update($request->validated());

        return new InsuranceProductResource($insuranceProduct);
    }

    public function destroy(InsuranceProduct $insuranceProduct): Response
    {
        Gate::authorize('delete', $insuranceProduct);

        $insuranceProduct->ensureHasNoOpenPolicies('A modalidade possui apólices em andamento. Inative-a em vez de excluí-la.');
        $insuranceProduct->delete();

        return response()->noContent();
    }
}
