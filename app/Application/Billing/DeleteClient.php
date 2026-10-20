<?php

namespace App\Application\Billing;

use App\Domain\Billing\ClientRepository;
use App\Domain\Billing\InvoiceRepository;
use App\Domain\NotFound;
use App\Domain\RuleViolation;

final readonly class DeleteClient
{
    public function __construct(private ClientRepository $clients, private InvoiceRepository $invoices) {}

    public function __invoke(int $id): void
    {
        $client = $this->clients->find($id) ?? throw new NotFound("Cliente $id");

        if ($this->invoices->existsForClient($id)) {
            throw new RuleViolation('Cliente tem faturas, exclua as faturas antes.');
        }

        $this->clients->delete($client);
    }
}
