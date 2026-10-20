import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { statusLabels } from '@/lib/invoices';
import { formatBRL } from '@/lib/money';
import type { InvoiceStatus } from '@/types/models';

type Props = {
    totals: Record<InvoiceStatus, { count: number; amount_cents: number }>;
};

const order: InvoiceStatus[] = ['open', 'overdue', 'paid', 'draft'];

export default function Dashboard({ totals }: Props) {
    return (
        <>
            <Head title="Painel" />
            <div className="flex flex-col gap-4 p-4">
                <Heading title="Painel" description="Faturas da sua empresa por status" />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {order.map((status) => (
                        <section key={status} aria-labelledby={`total-${status}`} className="rounded-xl border p-4">
                            <h3 id={`total-${status}`} className="text-sm text-muted-foreground">
                                {statusLabels[status]}
                            </h3>
                            <p className="mt-2 text-2xl font-semibold">{formatBRL(totals[status].amount_cents)}</p>
                            <p className="text-sm text-muted-foreground">
                                {totals[status].count} {totals[status].count === 1 ? 'fatura' : 'faturas'}
                            </p>
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = { breadcrumbs: [{ title: 'Painel', href: '/dashboard' }] };
