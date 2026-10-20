import type { InvoiceStatus } from '@/types/models';

export const statusLabels: Record<InvoiceStatus, string> = {
    draft: 'Rascunho',
    open: 'Em aberto',
    paid: 'Paga',
    overdue: 'Vencida',
};

export const formatDate = (isoDate: string): string => isoDate.split('-').reverse().join('/');
