<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

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
        return $this->view($user, $invoice) && $invoice->status !== InvoiceStatus::Paid;
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice)
            && in_array($invoice->status, [InvoiceStatus::Open, InvoiceStatus::Overdue], true);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->isOwner() && $this->view($user, $invoice);
    }
}
