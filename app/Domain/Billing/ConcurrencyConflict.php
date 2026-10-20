<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\RuleViolation;

/** Alguém salvou a fatura entre carregar e salvar de novo. Vira 409 na API e aviso na tela. */
final class ConcurrencyConflict extends RuleViolation
{
    public function __construct()
    {
        parent::__construct('A fatura mudou enquanto você mexia nela. Recarregue a página e tente de novo.');
    }
}
