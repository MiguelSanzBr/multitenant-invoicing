<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Application\Reminders\SendInvoiceReminder;
use App\Domain\Reminders\DeliveryOutcome;
use App\Domain\Reminders\IdempotencyKey;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/** Adapter fino da fila: quem decide o q fazer é o caso de uso, aqui só vira retry ou falha. */
final class SendInvoiceReminderJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

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

    public function uniqueId(): string
    {
        return IdempotencyKey::forInvoice($this->invoiceId, new DateTimeImmutable($this->day))->value;
    }

    public function handle(SendInvoiceReminder $send): void
    {
        $result = $send($this->invoiceId, new DateTimeImmutable($this->day));

        if ($result?->outcome === DeliveryOutcome::RetryLater) {
            throw new RuntimeException("Outbox indisponível ({$result->httpStatus}): {$result->error}");
        }

        if ($result?->outcome === DeliveryOutcome::Rejected) {
            $this->fail("Outbox recusou ({$result->httpStatus}): {$result->error}");
        }
    }
}
