<?php

namespace App\Domain\Billing;

interface ClientRepository
{
    /** Só enxerga a empresa do usuário atual. */
    public function find(int $id): ?Client;

    public function findAcrossTenants(int $id): ?Client;

    public function save(Client $client): Client;

    public function delete(Client $client): void;
}
