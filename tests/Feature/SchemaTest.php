<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Company;
use App\Infrastructure\Persistence\Models\Invoice;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// o banco segura as regras mesmo se alguém escrever por fora do domínio (seed, tinker, script de correção)
beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->other = Client::factory()->create();
});

function rawInvoice(array $overrides = []): int
{
    return DB::table('invoices')->insertGetId([
        'company_id' => test()->client->company_id, 'client_id' => test()->client->id, 'description' => 'Site',
        'amount_cents' => 1000, 'due_date' => '2026-10-10', 'status' => 'open', ...$overrides,
    ]);
}

function rawReminder(int $invoiceId, array $overrides = []): void
{
    DB::table('reminders')->insert([
        'company_id' => test()->client->company_id, 'invoice_id' => $invoiceId,
        'idempotency_key' => "invoice-$invoiceId-2026-10-20", 'outcome' => 'sent', ...$overrides,
    ]);
}

it('aceita fatura e lembrete coerentes', function () {
    $id = rawInvoice();
    rawReminder($id);
    rawInvoice(['status' => 'paid', 'paid_at' => '2026-10-20 10:00:00']);

    expect(DB::table('invoices')->count())->toBe(2);
});

it('recusa fatura de uma empresa apontando pra cliente de outra', function () {
    rawInvoice(['client_id' => $this->other->id]);
})->throws(QueryException::class, 'invoices_company_id_client_id_foreign');

it('recusa lembrete de uma empresa apontando pra fatura de outra', function () {
    $id = rawInvoice();

    rawReminder($id, ['company_id' => $this->other->company_id]);
})->throws(QueryException::class, 'reminders_company_id_invoice_id_foreign');

it('recusa valor, status e data de pagamento incoerentes', function (array $row, string $constraint) {
    expect(fn () => rawInvoice($row))->toThrow(QueryException::class, $constraint);
})->with([
    'valor zero' => [['amount_cents' => 0], 'invoices_amount_positive'],
    'valor negativo' => [['amount_cents' => -1], 'invoices_amount_positive'],
    'status inventado' => [['status' => 'cancelled'], 'invoices_status_valid'],
    'paga sem data' => [['status' => 'paid'], 'invoices_paid_at_matches_status'],
    'em aberto com data de pagamento' => [['paid_at' => '2026-10-20 10:00:00'], 'invoices_paid_at_matches_status'],
]);

it('recusa resultado de lembrete inventado', function () {
    rawReminder(rawInvoice(), ['outcome' => 'maybe']);
})->throws(QueryException::class, 'reminders_outcome_valid');

it('só um envio entregue por chave, tentativas repetidas podem', function () {
    $id = rawInvoice();
    rawReminder($id, ['outcome' => 'retry_later']);
    rawReminder($id, ['outcome' => 'retry_later']);
    rawReminder($id);
    rawReminder($id, ['outcome' => 'already_sent']);

    expect(fn () => rawReminder($id))->toThrow(QueryException::class, 'reminders_sent_key_unique');
});

it('ainda não apaga cliente com fatura, mas apaga a empresa inteira', function () {
    $invoice = Invoice::factory()->for($this->client)->create();
    rawReminder($invoice->id);

    expect(fn () => DB::table('clients')->where('id', $this->client->id)->delete())->toThrow(QueryException::class);

    Company::whereKey($this->client->company_id)->delete();

    expect(DB::table('invoices')->where('id', $invoice->id)->exists())->toBeFalse()
        ->and(DB::table('reminders')->count())->toBe(0);
});

it('tem índice nas listagens e nas FKs', function (string $table, array $columns) {
    $indexed = collect(Schema::getIndexes($table))->pluck('columns')->all();

    expect($indexed)->toContain($columns);
})->with([
    ['clients', ['company_id', 'name']],
    ['clients', ['company_id', 'id']],
    ['invoices', ['company_id', 'id']],
    ['invoices', ['company_id', 'client_id']],
    ['invoices', ['company_id', 'due_date', 'id']],
    ['reminders', ['company_id', 'invoice_id']],
    ['reminders', ['company_id', 'id']],
]);
