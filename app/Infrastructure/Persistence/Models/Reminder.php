<?php

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Reminders\DeliveryOutcome;
use App\Infrastructure\Persistence\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log de cada tentativa de envio, uma linha por tentativa.
 *
 * @property int $id
 * @property int $invoice_id
 * @property string $idempotency_key
 * @property DeliveryOutcome $outcome
 * @property int|null $http_status
 * @property string|null $error
 * @property CarbonImmutable $created_at
 * @property-read Invoice $invoice
 */
class Reminder extends Model
{
    use BelongsToCompany;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['outcome' => DeliveryOutcome::class, 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
