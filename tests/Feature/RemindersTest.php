<?php

declare(strict_types=1);

use App\Domain\Billing\Client as DomainClient;
use App\Domain\Billing\CrossTenantClients;
use App\Domain\Billing\CrossTenantInvoices;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\PhoneNumber;
use App\Domain\Reminders\DeliveryOutcome;
use App\Domain\Reminders\IdempotencyKey;
use App\Domain\Reminders\ReminderQueue;
use App\Infrastructure\Outbox\OutboxReminderSender;
use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Queue\SendInvoiceReminderJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.outbox.url' => 'http://outbox.test']);
    Http::preventStrayRequests();
    $this->travelTo(new DateTime('2026-10-20 09:00'));
    $this->client = Client::factory()->create(['name' => 'Ana', 'phone' => '5511999990000']);
    $this->invoice = Invoice::factory()->for($this->client)->create([
        'description' => 'Site', 'amount_cents' => 150000, 'due_date' => '2026-10-10', 'status' => InvoiceStatus::Overdue,
    ]);
});

function runJob(string $day = '2026-10-20'): SendInvoiceReminderJob
{
    $job = (new SendInvoiceReminderJob(test()->invoice->id, $day))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

function reminderRows(): array
{
    return DB::table('reminders')->orderBy('id')->get(['outcome', 'http_status', 'idempotency_key'])
        ->map(fn ($row) => (array) $row)->all();
}

it('manda pro outbox gateway com Idempotency-Key e o JSON esperado', function () {
    Http::fake(['outbox.test/*' => Http::response(['id' => 1, 'status' => 'queued'], 202)]);

    runJob()->assertNotFailed()->assertNotReleased();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://outbox.test/v1/messages'
        && $request->header('Idempotency-Key') === ["invoice-{$this->invoice->id}-2026-10-20"]
        && $request->data() === [
            'recipient' => '5511999990000',
            'body' => 'Olá Ana, a fatura "Site" de R$ 1.500,00 venceu em 10/10/2026.',
        ]);
    expect(reminderRows())->toBe([['outcome' => 'sent', 'http_status' => 202, 'idempotency_key' => "invoice-{$this->invoice->id}-2026-10-20"]]);
});

it('mapeia a resposta do gateway', function (int $status, DeliveryOutcome $outcome) {
    Http::fake(['outbox.test/*' => Http::response(['error' => 'x'], $status)]);

    $result = app(OutboxReminderSender::class)->send(IdempotencyKey::forInvoice(1, now()), new PhoneNumber('5511999990000'), 'oi');

    expect($result->outcome)->toBe($outcome)
        ->and($result->httpStatus)->toBe($status);
})->with([
    [202, DeliveryOutcome::Sent],
    [200, DeliveryOutcome::Sent],
    [409, DeliveryOutcome::AlreadySent],
    [500, DeliveryOutcome::RetryLater],
    [503, DeliveryOutcome::RetryLater],
    // 408 e 429 são o gateway sobrecarregado, não pedido ruim: tenta de novo
    [408, DeliveryOutcome::RetryLater],
    [429, DeliveryOutcome::RetryLater],
    [301, DeliveryOutcome::Rejected],
    [400, DeliveryOutcome::Rejected],
    [422, DeliveryOutcome::Rejected],
]);

it('respeita o Retry-After do gateway, em segundos ou data, com teto de 1h', function (array $headers, ?int $seconds) {
    Http::fake(['outbox.test/*' => Http::response('', 429, $headers)]);

    $result = app(OutboxReminderSender::class)->send(IdempotencyKey::forInvoice(1, now()), new PhoneNumber('5511999990000'), 'oi');

    expect($result->retryAfterSeconds)->toBe($seconds);
})->with([
    'segundos' => [['Retry-After' => '120'], 120],
    'data http' => [['Retry-After' => 'Tue, 20 Oct 2026 12:05:00 GMT'], 300],
    'teto' => [['Retry-After' => '86400'], 3600],
    'sem header' => [[], null],
    'lixo' => [['Retry-After' => 'amanhã'], null],
]);

it('manda com timeout e connect timeout da config', function () {
    config(['services.outbox.timeout' => 7, 'services.outbox.connect_timeout' => 2]);
    Http::fake(function (Request $request, array $options) {
        expect($options['timeout'])->toBe(7)
            ->and($options['connect_timeout'])->toBe(2);

        return Http::response([], 202);
    });

    expect(app(OutboxReminderSender::class)->send(IdempotencyKey::forInvoice(1, now()), new PhoneNumber('5511999990000'), 'oi')->outcome)
        ->toBe(DeliveryOutcome::Sent);
    Http::assertSentCount(1);
});

it('job: timeout, prazo, backoff, lock e disjuntor pinados', function () {
    $job = new SendInvoiceReminderJob(1, '2026-10-20');
    $breaker = $job->middleware()[0];
    $config = fn (string $property) => (new ReflectionProperty($breaker, $property))->getValue($breaker);

    expect($job->timeout)->toBe(30)
        ->and($job->uniqueFor)->toBe(86400)
        ->and($job->backoff())->toBe([60, 300, 900, 3600])
        ->and($job->retryUntil()->getTimestamp())->toBe(now()->addHours(12)->getTimestamp())
        ->and($job->middleware())->toHaveCount(1)
        ->and($breaker)->toBeInstanceOf(ThrottlesExceptions::class)
        ->and($config('maxAttempts'))->toBe(10)
        ->and($config('decaySeconds'))->toBe(300);
});

it('timeout ou gateway fora do ar vira retry', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $result = app(OutboxReminderSender::class)->send(IdempotencyKey::forInvoice(1, now()), new PhoneNumber('5511999990000'), 'oi');

    expect($result->outcome)->toBe(DeliveryOutcome::RetryLater)
        ->and($result->httpStatus)->toBeNull()
        ->and($result->error)->toBe('Connection refused');
});

it('5xx faz o job lançar exceção pra fila tentar de novo', function () {
    Http::fake(['outbox.test/*' => Http::response('', 503)]);

    expect(fn () => runJob())->toThrow(RuntimeException::class);
    expect(reminderRows()[0]['outcome'])->toBe('retry_later');
});

it('job falha sem retry quando o dado não fecha (cliente sumiu ou é de outra empresa)', function () {
    app()->instance(CrossTenantClients::class, new class implements CrossTenantClients
    {
        public function findAcrossTenants(int $id): ?DomainClient
        {
            return new DomainClient($id, 999, 'Outra empresa', null, new PhoneNumber('5511900000000'));
        }
    });
    Http::fake();

    runJob()->assertFailed();

    Http::assertNothingSent();
    expect(reminderRows())->toBeEmpty();
});

it('4xx falha o job sem retry', function () {
    Http::fake(['outbox.test/*' => Http::response(['error' => 'recipient inválido'], 422)]);

    runJob()->assertFailed();

    expect(reminderRows()[0]['outcome'])->toBe('rejected');
});

it('202 grava sent e a segunda rodada no mesmo dia nem chama o gateway', function () {
    Http::fake(['outbox.test/*' => Http::response([], 202)]);

    runJob()->assertNotFailed();
    runJob()->assertNotFailed();

    Http::assertSentCount(1);
    expect(reminderRows())->toHaveCount(1)
        ->and(reminderRows()[0]['outcome'])->toBe('sent');
});

it('409 conta como já enviado e não roda de novo no mesmo dia', function () {
    Http::fake(['outbox.test/*' => Http::response(['error' => 'conflict'], 409)]);

    runJob()->assertNotFailed();
    runJob()->assertNotFailed();

    Http::assertSentCount(1);
    expect(reminderRows())->toHaveCount(1)
        ->and(reminderRows()[0]['outcome'])->toBe('already_sent');
});

it('retry no dia seguinte mantém a chave do dia em q foi enfileirado', function () {
    Http::fake(['outbox.test/*' => Http::response([], 202)]);
    $this->travelTo(new DateTime('2026-10-21 00:30'));

    runJob('2026-10-20');

    Http::assertSent(fn (Request $request) => $request->header('Idempotency-Key') === ["invoice-{$this->invoice->id}-2026-10-20"]);
});

it('lock único por fatura e por dia segura o job duplicado na fila', function () {
    Queue::fake();

    SendInvoiceReminderJob::dispatch($this->invoice->id, '2026-10-20');
    SendInvoiceReminderJob::dispatch($this->invoice->id, '2026-10-20');
    SendInvoiceReminderJob::dispatch($this->invoice->id, '2026-10-21');

    expect(new SendInvoiceReminderJob(1, '2026-10-20'))->toBeInstanceOf(ShouldBeUnique::class)
        ->and((new SendInvoiceReminderJob(1, '2026-10-20'))->uniqueId())->toBe('invoice-1-2026-10-20');
    Queue::assertPushed(SendInvoiceReminderJob::class, 2);
});

it('retry_after de toda conexão de fila é maior q o timeout do job e o timeout HTTP é menor', function () {
    $job = new SendInvoiceReminderJob(1, '2026-10-20');

    $connections = collect(config('queue.connections'))->filter(fn (array $c) => isset($c['retry_after']));

    expect($connections)->not->toBeEmpty()
        ->and(config('services.outbox.timeout'))->toBeLessThan($job->timeout)
        ->and(config('services.outbox.connect_timeout'))->toBeLessThan(config('services.outbox.timeout'));
    $connections->each(fn (array $c, string $name) => expect($c['retry_after'])
        ->toBeGreaterThan($job->timeout, "retry_after da conexão $name precisa ser maior q o timeout do job"));
});

it('comando diário vence as faturas e enfileira os lembretes', function () {
    // só o job de lembrete é falso: o de bloco roda na fila síncrona e espalha os lembretes
    Queue::fake([SendInvoiceReminderJob::class]);
    $late = Invoice::factory()->for($this->client)->create(['due_date' => '2026-10-19', 'status' => InvoiceStatus::Open]);
    $future = Invoice::factory()->for($this->client)->create(['due_date' => '2026-10-30', 'status' => InvoiceStatus::Open]);

    $this->artisan('invoices:mark-overdue')->expectsOutput('1 fatura(s) marcada(s) como vencida(s).')->assertSuccessful();

    expect($late->fresh()?->status)->toBe(InvoiceStatus::Overdue)
        ->and($future->fresh()?->status)->toBe(InvoiceStatus::Open);
    Queue::assertPushed(SendInvoiceReminderJob::class, 2);
    Queue::assertPushed(SendInvoiceReminderJob::class, fn ($job) => $job->invoiceId === $late->id && $job->day === '2026-10-20');
});

it('ids das vencidas saem em blocos por id, sem carregar tudo de uma vez', function () {
    $more = Invoice::factory()->for($this->client)->count(2)->create(['status' => InvoiceStatus::Overdue]);
    Invoice::factory()->for($this->client)->create(['status' => InvoiceStatus::Open]);

    DB::enableQueryLog();
    $chunks = iterator_to_array(app(CrossTenantInvoices::class)->overdueIdChunks(2), false);

    expect($chunks)->toBe([[$this->invoice->id, $more[0]->id], [$more[1]->id]])
        ->and(DB::getQueryLog())->toHaveCount(2);
});

it('job de bloco enfileira um lembrete por fatura do bloco', function () {
    Queue::fake([SendInvoiceReminderJob::class]);

    app(ReminderQueue::class)->push([7, 8], new DateTimeImmutable('2026-10-20'));

    Queue::assertPushed(SendInvoiceReminderJob::class, 2);
    Queue::assertPushed(SendInvoiceReminderJob::class, fn ($job) => $job->invoiceId === 8 && $job->day === '2026-10-20');
});

it('marca as vencidas num UPDATE só, sem janela pra desfazer pagamento', function () {
    $late = Invoice::factory()->for($this->client)->create(['due_date' => '2026-10-19', 'status' => InvoiceStatus::Open]);
    $paid = Invoice::factory()->for($this->client)->paid()->create(['due_date' => '2026-10-01']);
    $today = Invoice::factory()->for($this->client)->create(['due_date' => '2026-10-20', 'status' => InvoiceStatus::Open]);

    DB::enableQueryLog();
    $marked = app(CrossTenantInvoices::class)->markOpenPastDueAsOverdue(new DateTimeImmutable('2026-10-20'));
    $queries = DB::getQueryLog();

    expect($marked)->toBe(1)
        ->and($queries)->toHaveCount(1)
        ->and($queries[0]['query'])->toStartWith('update')
        ->and($late->fresh()?->status)->toBe(InvoiceStatus::Overdue)
        ->and($paid->fresh()?->status)->toBe(InvoiceStatus::Paid)
        ->and($paid->fresh()?->paid_at)->not->toBeNull()
        ->and($today->fresh()?->status)->toBe(InvoiceStatus::Open);
});

it('comando de ponta a ponta com a fila síncrona grava o envio', function () {
    Http::fake(['outbox.test/*' => Http::response([], 202)]);

    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    expect(reminderRows()[0]['outcome'])->toBe('sent');
});

it('comando está agendado todo dia às 9h', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'invoices:mark-overdue'));

    expect($event?->expression)->toBe('0 9 * * *')
        // uma instância só mesmo com vários servidores, e o mutex expira em 1h se o processo morrer
        ->and($event?->onOneServer)->toBeTrue()
        ->and($event?->withoutOverlapping)->toBeTrue()
        ->and($event?->expiresAt)->toBe(60);
});

it('token expirado é limpo todo dia', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'sanctum:prune-expired'));

    expect($event?->expression)->toBe('0 3 * * *')
        ->and((string) $event?->command)->toContain('--hours=24')
        ->and($event?->onOneServer)->toBeTrue();
});
