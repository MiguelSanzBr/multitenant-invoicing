<?php

declare(strict_types=1);

namespace App\Infrastructure\Outbox;

use App\Domain\Billing\PhoneNumber;
use App\Domain\Reminders\DeliveryOutcome;
use App\Domain\Reminders\DeliveryResult;
use App\Domain\Reminders\IdempotencyKey;
use App\Domain\Reminders\ReminderSender;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/** Adapter HTTP pro POST /v1/messages do outbox-gateway-demo. */
final class OutboxReminderSender implements ReminderSender
{
    public function send(IdempotencyKey $key, PhoneNumber $recipient, string $body): DeliveryResult
    {
        try {
            $response = Http::baseUrl(Config::string('services.outbox.url'))
                ->timeout(Config::integer('services.outbox.timeout'))
                ->connectTimeout(Config::integer('services.outbox.connect_timeout'))
                ->acceptJson()
                ->withHeaders(['Idempotency-Key' => $key->value])
                ->post('/v1/messages', ['recipient' => $recipient->value, 'body' => $body]);
        } catch (ConnectionException $e) {
            // timeout ou gateway fora: não dá pra saber se chegou, então tenta de novo com a mesma chave
            return new DeliveryResult(DeliveryOutcome::RetryLater, null, $e->getMessage());
        }

        $status = $response->status();
        $outcome = match (true) {
            $status === 200, $status === 202 => DeliveryOutcome::Sent,
            // o gateway já tem essa chave: alguém mandou antes, conta como enviado
            $status === 409 => DeliveryOutcome::AlreadySent,
            // 408 e 429 são o gateway sobrecarregado, não pedido ruim
            $status === 408, $status === 429, $status >= 500 => DeliveryOutcome::RetryLater,
            default => DeliveryOutcome::Rejected,
        };

        return new DeliveryResult(
            $outcome,
            $status,
            $response->successful() ? null : mb_substr($response->body(), 0, 500),
            $outcome === DeliveryOutcome::RetryLater ? $this->retryAfter($response->header('Retry-After')) : null,
        );
    }

    // Retry-After vem em segundos ou como data HTTP; teto de 1h pra um header maluco não sumir com o lembrete
    private function retryAfter(string $header): ?int
    {
        $seconds = match (true) {
            ctype_digit($header) => (int) $header,
            ($at = strtotime($header)) !== false => $at - now()->getTimestamp(),
            default => null,
        };

        return $seconds === null ? null : max(0, min($seconds, 3600));
    }
}
