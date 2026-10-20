<?php

declare(strict_types=1);

namespace App\Domain;

use DomainException;

/** Regra de negócio quebrada, tipo pagar rascunho ou editar fatura paga. */
class RuleViolation extends DomainException {}
