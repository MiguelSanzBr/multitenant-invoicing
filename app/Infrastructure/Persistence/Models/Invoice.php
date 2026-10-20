<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Billing\InvoiceStatus;
use App\Infrastructure\Persistence\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $client_id
 * @property string $description
 * @property int $amount_cents
 * @property string $currency
 * @property CarbonImmutable $due_date
 * @property InvoiceStatus $status
 * @property CarbonImmutable|null $paid_at
 * @property int $version
 * @property-read Client $client
 *
 * Sem fillable de propósito: toda escrita passa pelo repositório com forceFill a partir do domínio.
 */
#[UseFactory(InvoiceFactory::class)]
class Invoice extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $attributes = [
        'currency' => 'BRL',
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        // amount_cents e version já chegam como int do pdo_pgsql, sem cast
        return [
            'due_date' => 'date:Y-m-d',
            'status' => InvoiceStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
