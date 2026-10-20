import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Client } from '@/types/models';

export default function ClientForm({ client }: { client?: Client }) {
    const { auth } = usePage().props;
    const form = useForm({
        name: client?.name ?? '',
        email: client?.email ?? '',
        phone: client?.phone ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (client) {
            form.put(`/clients/${client.id}`);
        } else {
            form.post('/clients');
        }
    };

    const destroy = () => {
        if (client && window.confirm(`Excluir ${client.name}?`)) {
            router.delete(`/clients/${client.id}`);
        }
    };

    const title = client ? 'Editar cliente' : 'Novo cliente';

    return (
        <>
            <Head title={title} />
            <form onSubmit={submit} className="flex max-w-xl flex-col gap-4 p-4">
                <Heading title={title} />

                <div className="grid gap-2">
                    <Label htmlFor="name">Nome</Label>
                    <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    <InputError message={form.errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">E-mail</Label>
                    <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                    <InputError message={form.errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="phone">Telefone (WhatsApp)</Label>
                    <Input id="phone" type="tel" inputMode="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} required />
                    <InputError message={form.errors.phone} />
                </div>

                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>Salvar</Button>
                    {client && auth.user.role === 'owner' && (
                        <Button type="button" variant="destructive" onClick={destroy}>Excluir</Button>
                    )}
                </div>
            </form>
        </>
    );
}

ClientForm.layout = { breadcrumbs: [{ title: 'Clientes', href: '/clients' }] };
