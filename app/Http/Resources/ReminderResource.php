<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reminder */
class ReminderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idempotency_key' => $this->idempotency_key,
            'outcome' => $this->outcome,
            'http_status' => $this->http_status,
            'error' => $this->error,
            'created_at' => $this->created_at->toIso8601String(),
            'invoice' => [
                'id' => $this->invoice->id,
                'description' => $this->invoice->description,
                'client' => ['name' => $this->invoice->client->name],
            ],
        ];
    }
}
