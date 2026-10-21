<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Billing\CreateInvoice;
use App\Application\Billing\DeleteInvoice;
use App\Application\Billing\MarkInvoicePaid;
use App\Application\Billing\UpdateInvoice;
use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Infrastructure\Persistence\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

// o caso de uso devolve a entidade, mas a resposta relê pelo model: leitura é Eloquent + Resource, igual às listas
class InvoiceController
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Invoice::class);

        return InvoiceResource::collection(Invoice::latest('due_date')->latest('id')->paginate(20));
    }

    public function store(InvoiceRequest $request, CreateInvoice $create): JsonResponse
    {
        Gate::authorize('create', Invoice::class);
        $invoice = $create($request->toData());

        return new InvoiceResource(Invoice::findOrFail($invoice->id))->response()->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        Gate::authorize('view', $invoice);

        return new InvoiceResource($invoice);
    }

    public function update(InvoiceRequest $request, Invoice $invoice, UpdateInvoice $update): InvoiceResource
    {
        Gate::authorize('update', $invoice);
        $update($invoice->id, $request->toData(), now());

        return new InvoiceResource($invoice->refresh());
    }

    public function pay(Invoice $invoice, MarkInvoicePaid $pay): InvoiceResource
    {
        Gate::authorize('pay', $invoice);
        $pay($invoice->id, now());

        return new InvoiceResource($invoice->refresh());
    }

    public function destroy(Invoice $invoice, DeleteInvoice $delete): Response
    {
        Gate::authorize('delete', $invoice);
        $delete($invoice->id);

        return response()->noContent();
    }
}
