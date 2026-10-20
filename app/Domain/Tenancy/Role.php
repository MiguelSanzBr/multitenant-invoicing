<?php

namespace App\Domain\Tenancy;

enum Role: string
{
    case Owner = 'owner';
    case Member = 'member';
}
