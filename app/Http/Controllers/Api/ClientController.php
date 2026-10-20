<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Billing\DeleteClient;
use App\Application\Billing\SaveClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Infrastructure\Persistence\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Client::class);

        return ClientResource::collection(Client::orderBy('name')->paginate(20));
    }

    public function store(ClientRequest $request, SaveClient $save): JsonResponse
    {
        Gate::authorize('create', Client::class);
        $client = $save(null, ...$request->fields());

        return new ClientResource(Client::findOrFail($client->id))->response()->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        Gate::authorize('update', $client);

        return new ClientResource($client);
    }

    public function update(ClientRequest $request, Client $client, SaveClient $save): ClientResource
    {
        Gate::authorize('update', $client);
        $save($client->id, ...$request->fields());

        return new ClientResource($client->refresh());
    }

    public function destroy(Client $client, DeleteClient $delete): Response
    {
        Gate::authorize('delete', $client);
        $delete($client->id);

        return response()->noContent();
    }
}
