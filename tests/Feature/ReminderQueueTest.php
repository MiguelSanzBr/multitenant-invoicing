<?php

declare(strict_types=1);

use App\Domain\Billing\InvoiceStatus;
use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Queue\SendInvoiceReminderJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// com a fila database de verdade: o q o sync e o Queue::fake não mostram (available_at, lock, failed_jobs, disjuntor)
beforeEach(function () {
    config(['queue.default' => 'database', 'services.outbox.url' => 'http://outbox.test']);
    Http::preventStrayRequests();
    $this->travelTo(new DateTime('2026-10-20 09:00'));
    $this->client = Client::factory()->create(['phone' => '5511999990000']);
    $this->invoice = Invoice::factory()->for($this->client)->create(['status' => InvoiceStatus::Overdue, 'due_date' => '2026-10-10']);
});

function work(): void
{
    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
}

function outcomes(): array
{
    return DB::table('reminders')->orderBy('id')->pluck('outcome')->all();
}

it('5xx volta pra fila com o backoff do job, depois sai e libera o lock', function () {
    Http::fakeSequence('outbox.test/*')->push('', 503)->push([], 202);

    SendInvoiceReminderJob::dispatch($this->invoice->id, '2026-10-20');
    work();

    $job = DB::table('jobs')->sole();
    expect($job->attempts)->toBe(1)
        ->and($job->available_at)->toBe(now()->addSeconds(60)->getTimestamp());

    $this->travel(61)->seconds();
    work();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(outcomes())->toBe(['retry_later', 'sent']);

    // lock liberado: o mesmo lembrete pode entrar na fila de novo (e o log local segura o reenvio)
    SendInvoiceReminderJob::dispatch($this->invoice->id, '2026-10-20');
    expect(DB::table('jobs')->count())->toBe(1);
});

it('429 com Retry-After volta pra fila no tempo q o gateway pediu', function () {
    Http::fake(['outbox.test/*' => Http::response('', 429, ['Retry-After' => '120'])]);

    SendInvoiceReminderJob::dispatch($this->invoice->id, '2026-10-20');
    work();

    expect(DB::table('jobs')->sole()->available_at)->toBe(now()->addSeconds(120)->getTimestamp());
});

it('4xx vai pro failed_jobs sem retry', function () {
    Http::fake(['outbox.test/*' => Http::response(['error' => 'recipient inválido'], 422)]);

    SendInvoiceReminderJob::dispatch($this->invoice->id, '2026-10-20');
    work();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(outcomes())->toBe(['rejected']);
});

it('disjuntor: depois de 10 falhas seguidas o job volta pra fila sem chamar o gateway', function () {
    Http::fake(['outbox.test/*' => Http::response('', 503)]);
    $invoices = Invoice::factory()->for($this->client)->count(10)->create(['status' => InvoiceStatus::Overdue, 'due_date' => '2026-10-10']);

    foreach ([$this->invoice, ...$invoices] as $invoice) {
        SendInvoiceReminderJob::dispatch($invoice->id, '2026-10-20');
    }
    foreach (range(1, 11) as $_) {
        work();
    }

    Http::assertSentCount(10);
    expect(DB::table('jobs')->count())->toBe(11)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('prazo de 12h: depois disso o job desiste e vai pro failed_jobs', function () {
    Http::fake(['outbox.test/*' => Http::response('', 503)]);

    SendInvoiceReminderJob::dispatch($this->invoice->id, '2026-10-20');
    $this->travel(13)->hours();
    work();

    expect(DB::table('failed_jobs')->count())->toBe(1);
    Http::assertNothingSent();
});
