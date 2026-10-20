<?php

namespace App\Domain\Billing;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case Overdue = 'overdue';
}
