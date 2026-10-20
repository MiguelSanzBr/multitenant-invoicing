import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { errorProps } from '@/lib/forms';
import { centsToInput, parseBRL } from '@/lib/money';
import clientsRoutes from '@/routes/clients';
import invoicesRoutes from '@/routes/invoices';
import type { Invoice } from '@/types/models';

type Props = {
    invoice?: Invoice;
    clients: Array<{ id: number; name: string }>;
};

const selectClass = 'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

export default function InvoiceForm({ invoice, clients }: Props) {
    const { auth } = usePage().props;
    const [amount, setAmount] = useState(invoice ? centsToInput(invoice.amount_cents) : '');
    const form = useForm({
        client_id: invoice ? String(invoice.client_id) : '',
        description: invoice?.description ?? '',
        amount_cents: invoice?.amount_cents ?? 0,
        due_date: invoice?.due_date ?? '',
        status: invoice?.status ?? 'draft',
    });
    // status só troca entre rascunho e em aberto; vencida e paga seguem o próprio caminho no domínio
    const canChangeStatus = !invoice || invoice.status === 'draft' || invoice.status === 'open';
    const locked = invoice?.status === 'paid';
    form.transform(({ status, ...data }) => (canChangeStatus ? { ...data, status } : data));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (parseBRL(amount) === null) {
            form.setError('amount_cents', 'Valor inválido.');

            return;
        }

        form.submit(invoice ? invoicesRoutes.update(invoice.id) : invoicesRoutes.store());
    };

    const changeAmount = (text: string) => {
        setAmount(text);
        form.setData('amount_cents', parseBRL(text) ?? 0);
    };

    const destroy = () => {
        if (invoice && window.confirm(`Excluir a fatura ${invoice.description}?`)) {
            router.delete(invoicesRoutes.destroy.url(invoice.id));
        }
    };

    const title = invoice ? 'Editar fatura' : 'Nova fatura';

    if (clients.length === 0) {
        return (
            <>
                <Head title={title} />
                <div className="flex max-w-xl flex-col gap-4 p-4">
                    <Heading title={title} />
                    <p className="text-muted-foreground">Cadastre um cliente antes de criar a fatura.</p>
                    <Button asChild className="w-fit">
                        <Link href={clientsRoutes.create.url()}>Cadastrar cliente</Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={title} />
            <form onSubmit={submit} className="flex max-w-xl flex-col gap-4 p-4">
                <Heading title={title} />
                {locked && <p className="text-sm text-muted-foreground">Fatura paga não pode ser editada.</p>}

                <fieldset disabled={locked} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="client_id">Cliente</Label>
                        <select id="client_id" {...errorProps('client_id', form.errors.client_id)} className={selectClass} value={form.data.client_id} onChange={(e) => form.setData('client_id', e.target.value)} required>
                            <option value="">Selecione</option>
                            {clients.map((client) => (
                                <option key={client.id} value={client.id}>{client.name}</option>
                            ))}
                        </select>
                        <InputError id="client_id-error" message={form.errors.client_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Descrição</Label>
                        <Input id="description" {...errorProps('description', form.errors.description)} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} required />
                        <InputError id="description-error" message={form.errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="amount">Valor (R$)</Label>
                        <Input id="amount" {...errorProps('amount', form.errors.amount_cents)} inputMode="decimal" placeholder="1.234,56" value={amount} onChange={(e) => changeAmount(e.target.value)} required />
                        <InputError id="amount-error" message={form.errors.amount_cents} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="due_date">Vencimento</Label>
                        <Input id="due_date" type="date" {...errorProps('due_date', form.errors.due_date)} value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} required />
                        <InputError id="due_date-error" message={form.errors.due_date} />
                    </div>

                    {canChangeStatus ? (
                        <div className="grid gap-2">
                            <Label htmlFor="status">Status</Label>
                            <select id="status" {...errorProps('status', form.errors.status)} className={selectClass} value={form.data.status} onChange={(e) => form.setData('status', e.target.value as Invoice['status'])}>
                                <option value="draft">Rascunho</option>
                                <option value="open">Em aberto</option>
                            </select>
                            <InputError id="status-error" message={form.errors.status} />
                        </div>
                    ) : (
                        invoice?.status === 'overdue' && (
                            <p className="text-sm text-muted-foreground">
                                Vencida. Se o vencimento for pra hoje ou depois, ela volta pra em aberto ao salvar.
                            </p>
                        )
                    )}
                </fieldset>

                <div className="flex gap-2">
                    {!locked && <Button type="submit" disabled={form.processing}>Salvar</Button>}
                    {invoice && auth.user.role === 'owner' && (
                        <Button type="button" variant="destructive" onClick={destroy}>Excluir</Button>
                    )}
                </div>
            </form>
        </>
    );
}

InvoiceForm.layout = { breadcrumbs: [{ title: 'Faturas', href: invoicesRoutes.index.url() }] };
