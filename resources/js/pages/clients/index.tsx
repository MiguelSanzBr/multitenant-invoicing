import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import clientsRoutes from '@/routes/clients';
import type { Client, Paginated } from '@/types/models';

export default function ClientsIndex({ clients }: { clients: Paginated<Client> }) {
    return (
        <>
            <Head title="Clientes" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <Heading title="Clientes" />
                    <Button asChild>
                        <Link href={clientsRoutes.create.url()}>Novo cliente</Link>
                    </Button>
                </div>

                {clients.data.length === 0 ? (
                    <p className="text-muted-foreground">Nenhum cliente cadastrado.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th scope="col" className="p-3">Nome</th>
                                    <th scope="col" className="p-3">E-mail</th>
                                    <th scope="col" className="p-3">Telefone</th>
                                </tr>
                            </thead>
                            <tbody>
                                {clients.data.map((client) => (
                                    <tr key={client.id} className="border-t">
                                        <td className="p-3 font-medium underline-offset-4 hover:underline">
                                            <Link href={clientsRoutes.edit.url(client.id)}>{client.name}</Link>
                                        </td>
                                        <td className="p-3">{client.email}</td>
                                        <td className="p-3">{client.phone}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination page={clients} />
            </div>
        </>
    );
}

ClientsIndex.layout = { breadcrumbs: [{ title: 'Clientes', href: clientsRoutes.index.url() }] };
