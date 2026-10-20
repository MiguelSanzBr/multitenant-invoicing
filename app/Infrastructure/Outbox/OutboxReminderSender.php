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
use Illuminate\Support\Facades\Log;

/** Adapter HTTP pro POST /v1/messages do outbox-gateway-demo. */
final class OutboxReminderSender implements ReminderSender
{
    public function send(IdempotencyKey $key, PhoneNumber $recipient, string $body): DeliveryResult
    {
        $idempotencyKey = Config::string('services.outbox.key_prefix').$key->value;

        try {
            $response = Http::baseUrl(Config::string('services.outbox.url'))
                ->timeout(Config::integer('services.outbox.timeout'))
                ->connectTimeout(Config::integer('services.outbox.connect_timeout'))
                ->acceptJson()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post('/v1/messages', ['recipient' => $recipient->value, 'body' => $body]);
        } catch (ConnectionException $e) {
            // timeout ou gateway fora: não dá pra saber se chegou, então tenta de novo com a mesma chave.
            // a mensagem crua (host e porta internos) fica no log, o tenant vê só o resumo
            Log::warning('outbox sem resposta', ['key' => $idempotencyKey, 'error' => $e->getMessage()]);

            return new DeliveryResult(DeliveryOutcome::RetryLater, null, 'Sem resposta do gateway.');
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

        if (! $response->successful()) {
            // o corpo pode ecoar o destinatário ou detalhe do provedor: só no log do servidor
            Log::warning('outbox recusou ou falhou', ['key' => $idempotencyKey, 'status' => $status, 'body' => mb_substr($response->body(), 0, 500)]);
        }

        return new DeliveryResult(
            $outcome,
            $status,
            $response->successful() ? null : "Gateway respondeu HTTP $status.",
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
