<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\DueDate;
use App\Domain\Billing\Invoice;
use App\Domain\Billing\InvoiceRepository;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\Money;
use App\Infrastructure\Persistence\Models\Invoice as InvoiceModel;
use App\Infrastructure\Persistence\Models\Scopes\CompanyScope;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final class EloquentInvoiceRepository implements InvoiceRepository
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
        $model = $invoice->id === null ? new InvoiceModel : $this->owned($invoice)->firstOrFail();
        $model->forceFill([
            'company_id' => $invoice->companyId,
            'client_id' => $invoice->clientId,
            'description' => $invoice->description,
            'amount_cents' => $invoice->amount->cents,
            'currency' => $invoice->amount->currency,
            'due_date' => (string) $invoice->dueDate,
            'status' => $invoice->status,
            'paid_at' => $invoice->paidAt,
        ])->save();

        return $this->toDomain($model);
    }

    public function delete(Invoice $invoice): void
    {
        $this->owned($invoice)->delete();
    }

    public function existsForClient(int $clientId): bool
    {
        return InvoiceModel::where('client_id', $clientId)->exists();
    }

    public function openPastDue(DateTimeImmutable $today): array
    {
        return array_values($this->allTenants()
            ->where('status', InvoiceStatus::Open)
            ->where('due_date', '<', $today->format('Y-m-d'))
            ->orderBy('id')
            ->get()
            ->map(fn (InvoiceModel $model) => $this->toDomain($model))
            ->all());
    }

    public function overdueIds(): array
    {
        return array_values($this->allTenants()
            ->where('status', InvoiceStatus::Overdue)
            ->orderBy('id')
            ->get(['id'])
            ->map(fn (InvoiceModel $model) => $model->id)
            ->all());
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
        );
    }
}
