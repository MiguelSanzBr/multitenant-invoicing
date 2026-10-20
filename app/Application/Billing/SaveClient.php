<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Billing\Client;
use App\Domain\Billing\ClientRepository;
use App\Domain\Billing\PhoneNumber;
use App\Domain\NotFound;
use App\Domain\Tenancy\TenantContext;

final readonly class SaveClient
{
    public function __construct(private ClientRepository $clients, private TenantContext $tenant) {}

    public function __invoke(?int $id, string $name, ?string $email, string $phone): Client
    {
        if ($id === null) {
            return $this->clients->save(Client::create($this->tenant->companyId(), $name, $email, new PhoneNumber($phone)));
        }

        $client = $this->clients->find($id) ?? throw new NotFound("Cliente $id");
        $client->update($name, $email, new PhoneNumber($phone));

        return $this->clients->save($client);
    }
}
