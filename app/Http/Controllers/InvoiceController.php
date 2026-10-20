<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\InvoiceRequest;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        return Inertia::render('invoices/index', [
            'invoices' => Invoice::with('client')->latest('due_date')->latest('id')->paginate(20),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Invoice::class);

        return $this->form();
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);
        Invoice::create($request->validated());

        return $this->done('Fatura salva.');
    }

    public function edit(Invoice $invoice): Response
    {
        Gate::authorize('update', $invoice);

        return $this->form($invoice);
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        $invoice->update($request->validated());

        return $this->done('Fatura salva.');
    }

    public function pay(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('pay', $invoice);
        $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fatura marcada como paga.']);

        return back();
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);
        $invoice->delete();

        return $this->done('Fatura excluída.');
    }

    private function form(?Invoice $invoice = null): Response
    {
        return Inertia::render('invoices/form', array_filter([
            'invoice' => $invoice,
            'clients' => Client::orderBy('name')->get(['id', 'name']),
        ]));
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('invoices.index');
    }
}
