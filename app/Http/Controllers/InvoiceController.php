<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Billing\CreateInvoice;
use App\Application\Billing\DeleteInvoice;
use App\Application\Billing\MarkInvoicePaid;
use App\Application\Billing\UpdateInvoice;
use App\Http\Requests\InvoiceRequest;
use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// leitura vai direto no model (com escopo de tenant), escrita passa pelo caso de uso
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

    public function store(InvoiceRequest $request, CreateInvoice $create): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);
        $create($request->toData());

        return $this->done('Fatura salva.');
    }

    public function edit(Invoice $invoice): Response
    {
        Gate::authorize('update', $invoice);

        return $this->form($invoice);
    }

    public function update(InvoiceRequest $request, Invoice $invoice, UpdateInvoice $update): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        $update($invoice->id, $request->toData(), now());

        return $this->done('Fatura salva.');
    }

    public function pay(Invoice $invoice, MarkInvoicePaid $pay): RedirectResponse
    {
        Gate::authorize('pay', $invoice);
        $pay($invoice->id, now());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fatura marcada como paga.']);

        return back();
    }

    public function destroy(Invoice $invoice, DeleteInvoice $delete): RedirectResponse
    {
        Gate::authorize('delete', $invoice);
        $delete($invoice->id);

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
