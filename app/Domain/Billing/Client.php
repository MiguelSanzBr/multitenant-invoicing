<?php

namespace App\Domain\Billing;

use App\Domain\RuleViolation;

final class Client
{
    public function __construct(
        public private(set) ?int $id,
        public readonly int $companyId,
        public private(set) string $name,
        public private(set) ?string $email,
        public private(set) PhoneNumber $phone,
    ) {
        self::guard($name);
    }

    public static function create(int $companyId, string $name, ?string $email, PhoneNumber $phone): self
    {
        return new self(null, $companyId, $name, $email, $phone);
    }

    public function update(string $name, ?string $email, PhoneNumber $phone): void
    {
        self::guard($name);
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
    }

    private static function guard(string $name): void
    {
        if (trim($name) === '') {
            throw new RuleViolation('Cliente precisa de nome.');
        }
    }
}
