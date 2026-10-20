import type { InvoiceStatus } from '@/types/models';

export const statusLabels: Record<InvoiceStatus, string> = {
    draft: 'Rascunho',
    open: 'Em aberto',
    paid: 'Paga',
    overdue: 'Vencida',
};

// cada status com cor própria pra ler a lista de relance
export const statusVariant: Record<InvoiceStatus, 'outline' | 'secondary' | 'default' | 'destructive'> = {
    draft: 'outline',
    open: 'secondary',
    paid: 'default',
    overdue: 'destructive',
};

export const formatDate = (isoDate: string): string => isoDate.split('-').reverse().join('/');

const dateTime = new Intl.DateTimeFormat('pt-BR', {
    timeZone: 'America/Sao_Paulo',
    dateStyle: 'short',
    timeStyle: 'short',
});

export const formatDateTime = (iso: string): string => dateTime.format(new Date(iso)).replace(',', '');
