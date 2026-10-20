<?php

declare(strict_types=1);

namespace App\Application\Billing;

final readonly class ClientData
{
    public function __construct(
        public string $name,
        public ?string $email,
        public string $phone,
    ) {}
}
