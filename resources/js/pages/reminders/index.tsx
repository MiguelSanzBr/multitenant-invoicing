import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { formatDateTime } from '@/lib/invoices';
import type { Paginated, Reminder, ReminderOutcome } from '@/types/models';

const outcomeLabels: Record<ReminderOutcome, string> = {
    sent: 'Enviado',
    already_sent: 'Já tinha sido enviado',
    retry_later: 'Vai tentar de novo',
    rejected: 'Recusado',
};

export default function RemindersIndex({ reminders }: { reminders: Paginated<Reminder> }) {
    return (
        <>
            <Head title="Lembretes" />
            <div className="flex flex-col gap-4 p-4">
                <Heading title="Lembretes" description="Cada tentativa de envio pelo outbox gateway" />

                {reminders.data.length === 0 ? (
                    <p className="text-muted-foreground">Nenhum lembrete enviado ainda.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th scope="col" className="p-3">Quando</th>
                                    <th scope="col" className="p-3">Fatura</th>
                                    <th scope="col" className="p-3">Cliente</th>
                                    <th scope="col" className="p-3">Resultado</th>
                                    <th scope="col" className="p-3">HTTP</th>
                                    <th scope="col" className="p-3">Chave</th>
                                    <th scope="col" className="p-3">Erro</th>
                                </tr>
                            </thead>
                            <tbody>
                                {reminders.data.map((reminder) => (
                                    <tr key={reminder.id} className="border-t">
                                        <td className="p-3 whitespace-nowrap">{formatDateTime(reminder.created_at)}</td>
                                        <td className="p-3 underline-offset-4 hover:underline">
                                            <Link href={`/invoices/${reminder.invoice.id}/edit`}>{reminder.invoice.description}</Link>
                                        </td>
                                        <td className="p-3">{reminder.invoice.client.name}</td>
                                        <td className="p-3">{outcomeLabels[reminder.outcome]}</td>
                                        <td className="p-3">{reminder.http_status ?? 'sem resposta'}</td>
                                        <td className="p-3 font-mono text-xs">{reminder.idempotency_key}</td>
                                        <td className="p-3 text-muted-foreground">{reminder.error}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination prev={reminders.prev_page_url} next={reminders.next_page_url} />
            </div>
        </>
    );
}

RemindersIndex.layout = { breadcrumbs: [{ title: 'Lembretes', href: '/reminders' }] };
