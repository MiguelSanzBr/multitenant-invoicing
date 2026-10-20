<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

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

    public function store(ClientRequest $request): RedirectResponse
    {
        Gate::authorize('create', Client::class);
        Client::create($request->validated());

        return $this->done('Cliente salvo.');
    }

    public function edit(Client $client): Response
    {
        Gate::authorize('update', $client);

        return Inertia::render('clients/form', ['client' => $client]);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);
        $client->update($request->validated());

        return $this->done('Cliente salvo.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        Gate::authorize('delete', $client);

        if ($client->invoices()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cliente tem faturas, exclua as faturas antes.']);

            return back();
        }

        $client->delete();

        return $this->done('Cliente excluído.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('clients.index');
    }
}
