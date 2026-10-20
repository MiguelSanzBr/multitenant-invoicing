import { BellRing, LayoutGrid, Receipt, Users } from 'lucide-react';
import { dashboard } from '@/routes';
import clients from '@/routes/clients';
import invoices from '@/routes/invoices';
import reminders from '@/routes/reminders';
import type { NavItem } from '@/types';

export const mainNavItems: NavItem[] = [
    { title: 'Painel', href: dashboard.url(), icon: LayoutGrid },
    { title: 'Clientes', href: clients.index.url(), icon: Users },
    { title: 'Faturas', href: invoices.index.url(), icon: Receipt },
    { title: 'Lembretes', href: reminders.index.url(), icon: BellRing },
];
