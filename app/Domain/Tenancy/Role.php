<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

enum Role: string
{
    case Owner = 'owner';
    case Member = 'member';
}
