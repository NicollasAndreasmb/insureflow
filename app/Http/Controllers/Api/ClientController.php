<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ListClientsRequest;
use App\Http\Requests\Client\SaveClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    public function index(ListClientsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Client::class);

        $clients = Client::query()
            ->withCount('policies')
            ->filter($request->filters())
            ->orderBy(...$request->sort())
            ->paginate($request->perPage());

        return ClientResource::collection($clients);
    }

    public function store(SaveClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return (new ClientResource($client))->response()->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        Gate::authorize('view', $client);

        $client->loadCount(['policies', 'claims'])->load([
            'policies' => fn ($query) => $query->with(['insurer', 'product'])->latest('starts_on'),
        ]);

        return new ClientResource($client);
    }

    public function update(SaveClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->validated());

        return new ClientResource($client);
    }

    public function destroy(Client $client): Response
    {
        Gate::authorize('delete', $client);

        $client->ensureHasNoOpenPolicies('O cliente possui apólices em andamento. Inative-o em vez de excluí-lo.');
        $client->delete();

        return response()->noContent();
    }
}
