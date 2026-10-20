<?php

namespace Database\Factories;

use App\Domain\Billing\InvoiceStatus;
use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'company_id' => fn (array $attributes) => Client::withoutGlobalScopes()->whereKey($attributes['client_id'])->value('company_id'),
            'description' => fake()->sentence(3),
            'amount_cents' => fake()->numberBetween(1000, 500000),
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => InvoiceStatus::Open,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => InvoiceStatus::Draft]);
    }

    public function paid(): static
    {
        return $this->state(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);
    }
}
