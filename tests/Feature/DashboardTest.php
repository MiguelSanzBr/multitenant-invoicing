<?php

declare(strict_types=1);

use App\Domain\Billing\InvoiceStatus;
use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Persistence\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('soma as faturas da empresa por status', function () {
    $user = User::factory()->create();
    $client = Client::factory()->for($user->company)->create();
    Invoice::factory()->for($client)->create(['amount_cents' => 1000, 'status' => InvoiceStatus::Open]);
    Invoice::factory()->for($client)->create(['amount_cents' => 2500, 'status' => InvoiceStatus::Open]);
    Invoice::factory()->for($client)->paid()->create(['amount_cents' => 700]);
    Invoice::factory()->create(['amount_cents' => 99999, 'status' => InvoiceStatus::Open]); // outra empresa

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('totals', [
                'draft' => ['count' => 0, 'amount_cents' => 0],
                'open' => ['count' => 2, 'amount_cents' => 3500],
                'paid' => ['count' => 1, 'amount_cents' => 700],
                'overdue' => ['count' => 0, 'amount_cents' => 0],
            ]));
});
