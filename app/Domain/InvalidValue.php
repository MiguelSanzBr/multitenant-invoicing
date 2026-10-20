<?php

declare(strict_types=1);

namespace App\Domain;

/** Value object recusou o valor (telefone, vencimento, dinheiro). Vira 422, igual erro de validação. */
final class InvalidValue extends RuleViolation {}
