<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Application\Reminders\SendInvoiceReminder;
use App\Domain\NotFound;
use App\Domain\Reminders\DeliveryOutcome;
use App\Domain\Reminders\IdempotencyKey;
use App\Domain\RuleViolation;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Throwable;

/** Adapter fino da fila: quem decide o q fazer é o caso de uso, aqui só vira retry ou falha. */
final class SendInvoiceReminderJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    // retry_after da conexão tem q ser maior, senão a fila entrega o mesmo job pra outro worker
    // enquanto o primeiro ainda espera o HTTP e o lembrete sai duas vezes
    public int $timeout = 30;

    // se o worker morrer segurando o lock, ele expira sozinho em um dia
    public int $uniqueFor = 86400;

    public function __construct(public readonly int $invoiceId, public readonly string $day) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    // prazo em vez de número de tentativas: com o disjuntor aberto o job volta pra fila sem chamar o
    // gateway, e isso não pode queimar as tentativas de quem nem tentou
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

    /**
     * Disjuntor: 10 falhas seguidas do gateway e os próximos jobs voltam pra fila por 5 min sem chamar.
     * Fora isso o atraso é o Retry-After do gateway ou o backoff exponencial acima.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new ThrottlesExceptions(10, 5 * 60))->backoff(fn (Throwable $e) => $this->delayAfter($e) / 60)];
    }

    private function delayAfter(Throwable $e): int
    {
        $backoff = $this->backoff();

        return $e instanceof OutboxUnavailable && $e->retryAfterSeconds !== null
            ? $e->retryAfterSeconds
            : $backoff[min($this->attempts(), count($backoff)) - 1];
    }

    public function uniqueId(): string
    {
        return IdempotencyKey::forInvoice($this->invoiceId, new DateTimeImmutable($this->day))->value;
    }

    public function handle(SendInvoiceReminder $send): void
    {
        try {
            $result = $send($this->invoiceId, new DateTimeImmutable($this->day));
        } catch (NotFound|RuleViolation $e) {
            // dado q não fecha não melhora tentando de novo
            $this->fail($e);

            return;
        }

        if ($result?->outcome === DeliveryOutcome::RetryLater) {
            throw new OutboxUnavailable("Outbox indisponível ({$result->httpStatus}): {$result->error}", $result->retryAfterSeconds);
        }

        if ($result?->outcome === DeliveryOutcome::Rejected) {
            $this->fail("Outbox recusou ({$result->httpStatus}): {$result->error}");
        }
    }
}
