<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/** Não existe ou é de outra empresa, propositalmente indistinguível. */
class NotFound extends RuntimeException {}
