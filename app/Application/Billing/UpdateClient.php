<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Billing\Client;
use App\Domain\Billing\ClientRepository;
use App\Domain\Billing\PhoneNumber;
use App\Domain\NotFound;

final readonly class UpdateClient
{
    public function __construct(private ClientRepository $clients) {}

    public function __invoke(int $id, ClientData $data): Client
    {
        $client = $this->clients->find($id) ?? throw new NotFound("Cliente $id");
        $client->update($data->name, $data->email, new PhoneNumber($data->phone));

        return $this->clients->save($client);
    }
}
