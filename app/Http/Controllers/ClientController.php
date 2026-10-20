<?php

namespace App\Http\Controllers;

use App\Application\Billing\DeleteClient;
use App\Application\Billing\SaveClient;
use App\Http\Requests\ClientRequest;
use App\Infrastructure\Persistence\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// leitura vai direto no model (com escopo de tenant), escrita passa pelo caso de uso
class ClientController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Client::class);

        return Inertia::render('clients/index', [
            'clients' => Client::orderBy('name')->paginate(20),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Client::class);

        return Inertia::render('clients/form');
    }

    public function store(ClientRequest $request, SaveClient $save): RedirectResponse
    {
        Gate::authorize('create', Client::class);
        $save(null, ...$request->fields());

        return $this->done('Cliente salvo.');
    }

    public function edit(Client $client): Response
    {
        Gate::authorize('update', $client);

        return Inertia::render('clients/form', ['client' => $client]);
    }

    public function update(ClientRequest $request, Client $client, SaveClient $save): RedirectResponse
    {
        Gate::authorize('update', $client);
        $save($client->id, ...$request->fields());

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
