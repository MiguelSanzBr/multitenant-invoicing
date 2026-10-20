import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, statusLabels, statusVariant } from '@/lib/invoices';
import { formatBRL } from '@/lib/money';
import invoicesRoutes from '@/routes/invoices';
import type { Invoice, Paginated } from '@/types/models';

export default function InvoicesIndex({ invoices }: { invoices: Paginated<Invoice> }) {
    // fatura sendo paga agora: o botão dela trava até a resposta, sem POST duplicado no duplo clique
    const [paying, setPaying] = useState<number | null>(null);

    const pay = (invoice: Invoice) => {
        if (paying !== null || !window.confirm(`Marcar a fatura ${invoice.description} como paga? Não dá pra desfazer.`)) {
            return;
        }

        router.post(invoicesRoutes.pay.url(invoice.id), {}, {
            preserveScroll: true,
            onStart: () => setPaying(invoice.id),
            onFinish: () => setPaying(null),
        });
    };

    return (
        <>
            <Head title="Faturas" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <Heading title="Faturas" />
                    <Button asChild>
                        <Link href={invoicesRoutes.create.url()}>Nova fatura</Link>
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
                                        <td className="p-3 font-medium">
                                            {invoice.status === 'paid' ? (
                                                invoice.description
                                            ) : (
                                                <Link href={invoicesRoutes.edit.url(invoice.id)} className="underline-offset-4 hover:underline">
                                                    {invoice.description}
                                                </Link>
                                            )}
                                        </td>
                                        <td className="p-3">{invoice.client?.name}</td>
                                        <td className="p-3 text-right whitespace-nowrap">{formatBRL(invoice.amount_cents)}</td>
                                        <td className="p-3">{formatDate(invoice.due_date)}</td>
                                        <td className="p-3">
                                            <Badge variant={statusVariant[invoice.status]}>
                                                {statusLabels[invoice.status]}
                                            </Badge>
                                        </td>
                                        <td className="p-3 text-right">
                                            {(invoice.status === 'open' || invoice.status === 'overdue') && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => pay(invoice)}
                                                    disabled={paying === invoice.id}
                                                    aria-label={`Marcar ${invoice.description} como paga`}
                                                >
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

                <Pagination page={invoices} />
            </div>
        </>
    );
}

InvoicesIndex.layout = { breadcrumbs: [{ title: 'Faturas', href: invoicesRoutes.index.url() }] };
