<?php

namespace Tests\Support;

use App\Domain\Billing\Client;
use App\Domain\Billing\ClientRepository;

final class InMemoryClients implements ClientRepository
{
    /** @var array<int, Client> */
    public array $items = [];

    // empresa q o find() enxerga, igual ao escopo global faz no Eloquent
    public function __construct(private int $companyId) {}

    public function find(int $id): ?Client
    {
        $client = $this->items[$id] ?? null;

        return $client?->companyId === $this->companyId ? $client : null;
    }

    public function save(Client $client): Client
    {
        $id = $client->id ?? count($this->items) + 1;

        return $this->items[$id] = new Client($id, $client->companyId, $client->name, $client->email, $client->phone);
    }

    public function delete(Client $client): void
    {
        unset($this->items[$client->id]);
    }
}
