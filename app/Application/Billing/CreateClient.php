<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Billing\Client;
use App\Domain\Billing\ClientRepository;
use App\Domain\Billing\PhoneNumber;
use App\Domain\Tenancy\TenantContext;

final readonly class CreateClient
{
    public function __construct(private ClientRepository $clients, private TenantContext $tenant) {}

    public function __invoke(ClientData $data): Client
    {
        return $this->clients->save(Client::create($this->tenant->companyId(), $data->name, $data->email, new PhoneNumber($data->phone)));
    }
}
