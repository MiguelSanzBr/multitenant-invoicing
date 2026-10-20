<?php

declare(strict_types=1);

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

    public const DESCRIPTIONS = [
        'Mensalidade de suporte técnico',
        'Desenvolvimento do site institucional',
        'Consultoria em processos financeiros',
        'Manutenção mensal do servidor',
        'Licença anual do sistema de gestão',
        'Treinamento da equipe de vendas',
        'Hospedagem e domínio do trimestre',
        'Ajustes no aplicativo de pedidos',
    ];

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'company_id' => fn (array $attributes) => Client::withoutGlobalScopes()->whereKey($attributes['client_id'])->value('company_id'),
            'description' => fake()->randomElement(self::DESCRIPTIONS),
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
