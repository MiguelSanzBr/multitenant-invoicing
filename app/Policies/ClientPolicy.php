<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

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
    public function update(User $user, Client $client): bool
    {
        return $user->company_id === $client->company_id;
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->isOwner() && $this->update($user, $client);
    }
}
