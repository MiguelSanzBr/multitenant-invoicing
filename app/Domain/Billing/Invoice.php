<?php

namespace App\Domain\Billing;

use App\Domain\RuleViolation;
use DateTimeImmutable;

final class Invoice
{
    public function __construct(
        public private(set) ?int $id,
        public readonly int $companyId,
        public private(set) int $clientId,
        public private(set) string $description,
        public private(set) Money $amount,
        public private(set) DueDate $dueDate,
        public private(set) InvoiceStatus $status,
        public private(set) ?DateTimeImmutable $paidAt = null,
    ) {
        self::guard($description, $amount);
    }

    public static function create(int $companyId, int $clientId, string $description, Money $amount, DueDate $dueDate, InvoiceStatus $status): self
    {
        $invoice = new self(null, $companyId, $clientId, $description, $amount, $dueDate, InvoiceStatus::Draft);

        match ($status) {
            InvoiceStatus::Draft => null,
            InvoiceStatus::Open => $invoice->open(),
            default => throw new RuleViolation('Fatura nova começa como rascunho ou em aberto.'),
        };

        return $invoice;
    }

    public function open(): void
    {
        if ($this->status !== InvoiceStatus::Draft) {
            throw new RuleViolation('Só rascunho pode ser aberto.');
        }

        $this->status = InvoiceStatus::Open;
    }

    public function markPaid(DateTimeImmutable $at): void
    {
        if (! in_array($this->status, [InvoiceStatus::Open, InvoiceStatus::Overdue], true)) {
            throw new RuleViolation('Só fatura em aberto ou vencida pode ser paga.');
        }

        $this->status = InvoiceStatus::Paid;
        $this->paidAt = $at;
    }

    public function markOverdue(DateTimeImmutable $today): void
    {
        if ($this->status !== InvoiceStatus::Open || ! $this->dueDate->isBefore($today)) {
            throw new RuleViolation('Só fatura em aberto com vencimento no passado vence.');
        }

        $this->status = InvoiceStatus::Overdue;
    }

    public function edit(int $clientId, string $description, Money $amount, DueDate $dueDate, InvoiceStatus $status): void
    {
        if ($this->status === InvoiceStatus::Paid) {
            throw new RuleViolation('Fatura paga não pode ser editada.');
        }

        // pago e vencido têm caminho próprio (markPaid e o comando diário), edição não pula etapa
        if (! in_array($status, [InvoiceStatus::Draft, InvoiceStatus::Open], true)) {
            throw new RuleViolation('Na edição a fatura só pode ficar como rascunho ou em aberto.');
        }

        self::guard($description, $amount);
        $this->clientId = $clientId;
        $this->description = $description;
        $this->amount = $amount;
        $this->dueDate = $dueDate;
        $this->status = $status;
    }

    private static function guard(string $description, Money $amount): void
    {
        if (trim($description) === '' || $amount->cents === 0) {
            throw new RuleViolation('Fatura precisa de descrição e valor maior que zero.');
        }
    }
}
