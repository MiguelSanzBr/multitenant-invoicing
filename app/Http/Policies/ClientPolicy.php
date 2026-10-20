<?php

declare(strict_types=1);

namespace App\Http\Policies;

use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\User;

// policy responde quem pode. Regra de estado (pode pagar? pode editar?) é do domínio
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    // o escopo já esconde o q é de outra empresa, a policy confere de novo pq não custa nada
    public function view(User $user, Client $client): bool
    {
        return $user->company_id === $client->company_id;
    }

    public function update(User $user, Client $client): bool
    {
        return $this->view($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->isOwner() && $this->update($user, $client);
    }
}
