<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// onOneServer: com mais de uma instância rodando schedule:run, só uma marca e enfileira.
// o mutex expira em 60 min: se o processo morrer no meio, a próxima rodada não fica travada por um dia
Schedule::command('invoices:mark-overdue')->dailyAt('09:00')->onOneServer()->withoutOverlapping(60);

Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('03:00')->onOneServer();
