<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Billing\CreateClient;
use App\Application\Billing\DeleteClient;
use App\Application\Billing\UpdateClient;
use App\Http\Requests\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Infrastructure\Persistence\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// leitura vai direto no model (com escopo de tenant) e sai pelo Resource, escrita passa pelo caso de uso
class ClientController
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Client::class);

        return Inertia::render('clients/index', [
            'clients' => Client::orderBy('name')->paginate(20)->through(fn (Client $client) => (new ClientResource($client))->resolve()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Client::class);

        return Inertia::render('clients/form');
    }

    public function store(ClientRequest $request, CreateClient $create): RedirectResponse
    {
        Gate::authorize('create', Client::class);
        $create($request->toData());

        return $this->done('Cliente salvo.');
    }

    public function edit(Client $client): Response
    {
        Gate::authorize('update', $client);

        return Inertia::render('clients/form', ['client' => (new ClientResource($client))->resolve()]);
    }

    public function update(ClientRequest $request, Client $client, UpdateClient $update): RedirectResponse
    {
        Gate::authorize('update', $client);
        $update($client->id, $request->toData());

        return $this->done('Cliente salvo.');
    }

    public function destroy(Client $client, DeleteClient $delete): RedirectResponse
    {
        Gate::authorize('delete', $client);
        $delete($client->id);

        return $this->done('Cliente excluído.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('clients.index');
    }
}
