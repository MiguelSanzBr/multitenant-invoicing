<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Billing\CreateClient;
use App\Application\Billing\DeleteClient;
use App\Application\Billing\UpdateClient;
use App\Http\Requests\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Infrastructure\Persistence\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

// o caso de uso devolve a entidade, mas a resposta relê pelo model: leitura é Eloquent + Resource, igual às listas
class ClientController
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Client::class);

        return ClientResource::collection(Client::orderBy('name')->paginate(20));
    }

    public function store(ClientRequest $request, CreateClient $create): JsonResponse
    {
        Gate::authorize('create', Client::class);
        $client = $create($request->toData());

        return new ClientResource(Client::findOrFail($client->id))->response()->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        Gate::authorize('view', $client);

        return new ClientResource($client);
    }

    public function update(ClientRequest $request, Client $client, UpdateClient $update): ClientResource
    {
        Gate::authorize('update', $client);
        $update($client->id, $request->toData());

        return new ClientResource($client->refresh());
    }

    public function destroy(Client $client, DeleteClient $delete): Response
    {
        Gate::authorize('delete', $client);
        $delete($client->id);

        return response()->noContent();
    }
}
