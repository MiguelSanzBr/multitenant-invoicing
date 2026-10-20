<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use RuntimeException;

/** Gateway fora ou sobrecarregado. Carrega o Retry-After pra o disjuntor devolver o job pra fila no tempo certo. */
final class OutboxUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
    }
}
