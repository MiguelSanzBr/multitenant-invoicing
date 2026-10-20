<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\Client;
use App\Domain\Billing\ConcurrencyConflict;
use App\Domain\Billing\CrossTenantInvoices;
use App\Domain\Billing\DueDate;
use App\Domain\Billing\Invoice;
use App\Domain\Billing\InvoiceRepository;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\Money;
use App\Infrastructure\Persistence\Models\Invoice as InvoiceModel;
use App\Infrastructure\Persistence\Models\Scopes\CompanyScope;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final class EloquentInvoiceRepository implements CrossTenantInvoices, InvoiceRepository
{
    public function find(int $id): ?Invoice
    {
        $model = InvoiceModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findAcrossTenants(int $id): ?Invoice
    {
        $model = $this->allTenants()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Invoice $invoice): Invoice
    {
        $attributes = [
            'company_id' => $invoice->companyId,
            'client_id' => $invoice->clientId,
            'description' => $invoice->description,
            'amount_cents' => $invoice->amount->cents,
            'currency' => $invoice->amount->currency,
            'due_date' => (string) $invoice->dueDate,
            'status' => $invoice->status,
            'paid_at' => $invoice->paidAt,
        ];

        if ($invoice->id === null) {
            $model = new InvoiceModel;
            $model->forceFill([...$attributes, 'version' => 0])->save();

            return $this->toDomain($model);
        }

        // só grava se ninguém salvou depois q essa cópia foi carregada
        $updated = $this->owned($invoice)
            ->where('version', $invoice->version)
            ->update([...$attributes, 'version' => $invoice->version + 1]);

        if ($updated === 0) {
            throw new ConcurrencyConflict;
        }

        return $this->toDomain($this->owned($invoice)->firstOrFail());
    }

    public function delete(Invoice $invoice): void
    {
        $this->owned($invoice)->delete();
    }

    public function existsForClient(Client $client): bool
    {
        return $this->allTenants()->where('company_id', $client->companyId)->where('client_id', $client->id)->exists();
    }

    public function markOpenPastDueAsOverdue(DateTimeImmutable $today): int
    {
        return $this->allTenants()
            ->where('status', InvoiceStatus::Open)
            ->where('due_date', '<', $today->format('Y-m-d'))
            ->update(['status' => InvoiceStatus::Overdue, 'version' => DB::raw('version + 1')]);
    }

    public function overdueIdChunks(int $size): iterable
    {
        return $this->allTenants()
            ->where('status', InvoiceStatus::Overdue)
            ->select('id')
            ->lazyById($size)
            ->map(fn (InvoiceModel $model) => $model->id)
            ->chunk($size)
            ->map(fn (LazyCollection $ids) => array_values($ids->all()));
    }

    /**
     * @return Builder<InvoiceModel>
     */
    private function owned(Invoice $invoice): Builder
    {
        return $this->allTenants()->where('company_id', $invoice->companyId)->whereKey($invoice->id);
    }

    /**
     * @return Builder<InvoiceModel>
     */
    private function allTenants(): Builder
    {
        return InvoiceModel::withoutGlobalScope(CompanyScope::class);
    }

    private function toDomain(InvoiceModel $model): Invoice
    {
        return new Invoice(
            $model->id,
            $model->company_id,
            $model->client_id,
            $model->description,
            new Money($model->amount_cents, $model->currency),
            DueDate::fromString($model->due_date->format('Y-m-d')),
            $model->status,
            $model->paid_at,
            $model->version,
        );
    }
}
