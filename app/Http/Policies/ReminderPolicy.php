<?php

declare(strict_types=1);

namespace App\Http\Policies;

use App\Infrastructure\Persistence\Models\User;

// histórico é só leitura e o escopo já filtra a empresa
class ReminderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
}
