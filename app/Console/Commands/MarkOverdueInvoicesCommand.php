<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Billing\MarkOverdueInvoices;
use Illuminate\Console\Command;

class MarkOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Marca faturas vencidas e enfileira os lembretes do dia';

    public function handle(MarkOverdueInvoices $mark): int
    {
        $marked = $mark(now());
        $this->info("$marked fatura(s) marcada(s) como vencida(s).");

        return self::SUCCESS;
    }
}
