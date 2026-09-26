<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installment\ListInstallmentsRequest;
use App\Http\Requests\Installment\PayInstallmentRequest;
use App\Http\Resources\InstallmentResource;
use App\Models\Installment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Acompanhamento financeiro: parcelas são geradas pelo PolicyService, então
 * aqui só há consulta e registro de pagamento.
 */
class InstallmentController extends Controller
{
    public function index(ListInstallmentsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Installment::class);

        $installments = Installment::query()
            ->with('policy.client')
            ->filter($request->filters())
            ->orderBy(...$request->sort())
            ->orderBy('id')
            ->paginate($request->perPage());

        return InstallmentResource::collection($installments);
    }

    public function show(Installment $installment): InstallmentResource
    {
        Gate::authorize('view', $installment);

        return new InstallmentResource($installment->load('policy.client'));
    }

    public function pay(PayInstallmentRequest $request, Installment $installment): InstallmentResource
    {
        $installment->registerPayment($request->paidOn());

        return new InstallmentResource($installment->load('policy.client'));
    }
}
