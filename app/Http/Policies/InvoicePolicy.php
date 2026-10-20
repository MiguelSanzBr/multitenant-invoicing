<?php

declare(strict_types=1);

namespace App\Http\Policies;

use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Persistence\Models\User;

// policy responde quem pode. Regra de estado (pode pagar? pode editar?) é do domínio
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->isOwner() && $this->view($user, $invoice);
    }
}
