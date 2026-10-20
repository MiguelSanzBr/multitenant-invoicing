<?php

namespace App\Http\Controllers;

use App\Domain\Billing\InvoiceStatus;
use App\Infrastructure\Persistence\Models\Invoice;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // toBase() mantém o escopo de tenant, então a soma é só da empresa logada
        /** @var Collection<string, object{count: int|string, amount_cents: int|string}> $rows */
        $rows = Invoice::query()->toBase()
            ->selectRaw('status, count(*) as count, coalesce(sum(amount_cents), 0) as amount_cents')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $totals = [];
        foreach (InvoiceStatus::cases() as $status) {
            $row = $rows->get($status->value);
            $totals[$status->value] = [
                'count' => (int) ($row->count ?? 0),
                'amount_cents' => (int) ($row->amount_cents ?? 0),
            ];
        }

        return Inertia::render('dashboard', ['totals' => $totals]);
    }
}
