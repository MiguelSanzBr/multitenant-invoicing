<?php

use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Persistence\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function logReminder(Invoice $invoice, string $outcome, ?int $status = 202): void
{
    DB::table('reminders')->insert([
        'company_id' => $invoice->company_id, 'invoice_id' => $invoice->id, 'idempotency_key' => "invoice-{$invoice->id}-2026-10-20",
        'outcome' => $outcome, 'http_status' => $status, 'error' => $status === 503 ? 'indisponível' : null,
    ]);
}

it('mostra o histórico de lembretes da empresa, mais recente primeiro', function () {
    $user = User::factory()->create();
    $mine = Invoice::factory()->for(Client::factory()->for($user->company)->create(['name' => 'Ana']))->create(['description' => 'Site']);
    logReminder($mine, 'retry_later', 503);
    logReminder($mine, 'sent');
    logReminder(Invoice::factory()->create(), 'sent'); // outra empresa

    $this->actingAs($user)->get(route('reminders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reminders/index')
            ->has('reminders.data', 2)
            ->where('reminders.data.0.outcome', 'sent')
            ->where('reminders.data.0.invoice.description', 'Site')
            ->where('reminders.data.0.invoice.client.name', 'Ana')
            ->where('reminders.data.1.error', 'indisponível'));
});
