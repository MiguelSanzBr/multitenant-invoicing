<?php

declare(strict_types=1);

namespace App\Domain\Reminders;

final readonly class DeliveryResult
{
    public function __construct(
        public DeliveryOutcome $outcome,
        public ?int $httpStatus = null,
        public ?string $error = null,
        // quanto o gateway pediu pra esperar (Retry-After), só faz sentido no RetryLater
        public ?int $retryAfterSeconds = null,
    ) {}
}
