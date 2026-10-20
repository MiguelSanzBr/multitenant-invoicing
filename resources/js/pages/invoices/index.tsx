import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, statusLabels } from '@/lib/invoices';
import { formatBRL } from '@/lib/money';
import type { Invoice, Paginated } from '@/types/models';

export default function InvoicesIndex({ invoices }: { invoices: Paginated<Invoice> }) {
    const pay = (invoice: Invoice) => router.post(`/invoices/${invoice.id}/pay`, {}, { preserveScroll: true });

    return (
        <>
            <Head title="Faturas" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <Heading title="Faturas" />
                    <Button asChild>
                        <Link href="/invoices/create">Nova fatura</Link>
                    </Button>
                </div>

                {invoices.data.length === 0 ? (
                    <p className="text-muted-foreground">Nenhuma fatura cadastrada.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th scope="col" className="p-3">Descrição</th>
                                    <th scope="col" className="p-3">Cliente</th>
                                    <th scope="col" className="p-3 text-right">Valor</th>
                                    <th scope="col" className="p-3">Vencimento</th>
                                    <th scope="col" className="p-3">Status</th>
                                    <th scope="col" className="p-3"><span className="sr-only">Ações</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.data.map((invoice) => (
                                    <tr key={invoice.id} className="border-t">
                                        <td className="p-3 font-medium underline-offset-4 hover:underline">
                                            <Link href={`/invoices/${invoice.id}/edit`}>{invoice.description}</Link>
                                        </td>
                                        <td className="p-3">{invoice.client?.name}</td>
                                        <td className="p-3 text-right whitespace-nowrap">{formatBRL(invoice.amount_cents)}</td>
                                        <td className="p-3">{formatDate(invoice.due_date)}</td>
                                        <td className="p-3">
                                            <Badge variant={invoice.status === 'overdue' ? 'destructive' : 'secondary'}>
                                                {statusLabels[invoice.status]}
                                            </Badge>
                                        </td>
                                        <td className="p-3 text-right">
                                            {(invoice.status === 'open' || invoice.status === 'overdue') && (
                                                <Button size="sm" variant="outline" onClick={() => pay(invoice)}>
                                                    Marcar como paga
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination prev={invoices.prev_page_url} next={invoices.next_page_url} />
            </div>
        </>
    );
}

InvoicesIndex.layout = { breadcrumbs: [{ title: 'Faturas', href: '/invoices' }] };
