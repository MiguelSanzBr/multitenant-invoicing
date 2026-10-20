<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Recebe um bloco de faturas e espalha um job de lembrete por fatura, assim o comando diário fica O(blocos). */
final class QueueInvoiceRemindersJob implements ShouldQueue
{
    use Queueable;

    /** @param list<int> $invoiceIds */
    public function __construct(public readonly array $invoiceIds, public readonly string $day) {}

    public function handle(): void
    {
        // dispatch um por um (e não bulk) pq é ele q pega o lock único de cada fatura e dia
        foreach ($this->invoiceIds as $id) {
            SendInvoiceReminderJob::dispatch($id, $this->day);
        }
    }
}
