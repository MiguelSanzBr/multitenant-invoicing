<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Billing\ClientRepository;
use App\Domain\Billing\DueDate;
use App\Domain\Billing\Invoice;
use App\Domain\Billing\InvoiceRepository;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\Money;
use App\Domain\NotFound;
use App\Domain\Tenancy\TenantContext;

final readonly class CreateInvoice
{
    public function __construct(
        private InvoiceRepository $invoices,
        private ClientRepository $clients,
        private TenantContext $tenant,
    ) {}

    public function __invoke(InvoiceData $data): Invoice
    {
        // o find é por empresa: cliente de outro tenant cai aqui mesmo se a validação do form falhar
        $this->clients->find($data->clientId) ?? throw new NotFound("Cliente {$data->clientId}");

        return $this->invoices->save(Invoice::create(
            $this->tenant->companyId(),
            $data->clientId,
            $data->description,
            Money::brl($data->amountCents),
            DueDate::fromString($data->dueDate),
            $data->status ?? InvoiceStatus::Draft,
        ));
    }
}
