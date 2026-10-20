<?php

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'description' => $this->description,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'due_date' => $this->due_date->format('Y-m-d'),
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
