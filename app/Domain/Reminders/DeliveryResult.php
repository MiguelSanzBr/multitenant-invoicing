<?php

namespace App\Domain\Reminders;

final readonly class DeliveryResult
{
    public function __construct(
        public DeliveryOutcome $outcome,
        public ?int $httpStatus = null,
        public ?string $error = null,
    ) {}
}
